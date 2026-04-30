<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Voucher;
use App\Services\PakasirService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Cart sederhana untuk user login.
 *
 * Pembayaran satu transaksi (Pakasir) menggabungkan grand total dari semua
 * item di keranjang menjadi 1 Order parent + N OrderItem. Saat webhook PAID
 * masuk, OrderFulfillment iterasi tiap item & assign stok per varian.
 * Guest TIDAK diijinkan mengakses cart (qty = 1, beli langsung).
 */
class CartController extends Controller
{
    public function __construct(protected PakasirService $pakasir) {}

    public function index(): View
    {
        $items = CartItem::with(['product', 'variant'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('cart.index', compact('items'));
    }

    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        /** @var ProductVariant $variant */
        $variant = ProductVariant::with('product')->findOrFail($data['product_variant_id']);

        $userId = Auth::id();

        $item = CartItem::firstOrNew([
            'user_id' => $userId,
            'product_variant_id' => $variant->id,
        ]);

        $item->product_id = $variant->product_id;
        $item->quantity = min(10, ($item->quantity ?? 0) + (int) ($data['quantity'] ?? 1));
        $item->save();

        $message = "{$variant->product?->name} ({$variant->name}) ditambahkan ke keranjang.";

        if ($request->wantsJson() || $request->boolean('json')) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'cart' => $this->cartSummary(),
            ]);
        }

        return redirect()->route('cart.index')->with('success', $message);
    }

    /**
     * GET /keranjang/summary — JSON summary cart untuk popup mini.
     */
    public function summary(): JsonResponse
    {
        return response()->json($this->cartSummary());
    }

    /**
     * @return array{count:int,subtotal:int,subtotal_formatted:string,items:array<int,array<string,mixed>>}
     */
    protected function cartSummary(): array
    {
        $items = CartItem::with(['product', 'variant'])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        $count = 0;
        $subtotal = 0;
        $rows = [];
        foreach ($items as $cart) {
            if (! $cart->variant) {
                continue;
            }
            $unit = (int) $cart->variant->effectivePrice();
            $qty = max(1, (int) $cart->quantity);
            $line = $unit * $qty;
            $count += $qty;
            $subtotal += $line;
            $rows[] = [
                'id' => $cart->id,
                'product' => $cart->product?->name,
                'variant' => $cart->variant?->name,
                'qty' => $qty,
                'unit_price' => $unit,
                'unit_price_formatted' => 'Rp '.number_format($unit, 0, ',', '.'),
                'line_total' => $line,
                'line_total_formatted' => 'Rp '.number_format($line, 0, ',', '.'),
                'remove_url' => route('cart.destroy', $cart->id),
            ];
        }

        return [
            'count' => $count,
            'distinct' => count($rows),
            'subtotal' => $subtotal,
            'subtotal_formatted' => 'Rp '.number_format($subtotal, 0, ',', '.'),
            'items' => $rows,
            'cart_url' => route('cart.index'),
        ];
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        abort_unless($item->user_id === Auth::id(), 403);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $item->quantity = (int) $data['quantity'];
        $item->save();

        return back()->with('success', 'Keranjang diperbarui.');
    }

    public function destroy(CartItem $item): RedirectResponse
    {
        abort_unless($item->user_id === Auth::id(), 403);
        $item->delete();

        return back()->with('success', 'Item dihapus dari keranjang.');
    }

    /**
     * POST /keranjang/checkout — bayar SEMUA item di keranjang dalam 1 transaksi.
     */
    public function checkoutAll(Request $request): RedirectResponse
    {
        // Tolak user banned.
        if (Auth::check() && Auth::user()->is_banned) {
            Auth::logout();

            return redirect()->route('login')->with('error', 'Akun Anda di-banned. Tidak bisa checkout.');
        }

        $data = $request->validate([
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\- ]+$/'],
            'voucher_code' => ['nullable', 'string', 'max:64'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $items = CartItem::with(['variant.product'])
            ->where('user_id', $user->id)
            ->get();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang kosong.');
        }

        // Hitung grand total di server pakai harga efektif (flashsale-aware).
        $subtotal = 0;
        $lineTotals = [];
        foreach ($items as $cart) {
            if (! $cart->variant) {
                return redirect()->route('cart.index')->with('error', 'Salah satu varian tidak tersedia lagi.');
            }
            $unit = $cart->variant->effectivePrice();
            $qty = max(1, (int) $cart->quantity);
            $line = $unit * $qty;
            $subtotal += $line;
            $lineTotals[$cart->id] = ['unit' => $unit, 'qty' => $qty, 'line' => $line];
        }

        $discount = 0;
        $voucher = null;
        if (! empty($data['voucher_code'])) {
            $voucher = Voucher::active()
                ->whereRaw('LOWER(code) = ?', [strtolower(trim($data['voucher_code']))])
                ->first();
            if (! $voucher) {
                return back()->withInput()->withErrors([
                    'voucher_code' => 'Kode voucher tidak ditemukan atau sudah kadaluarsa.',
                ]);
            }
            $discount = $voucher->discountFor($subtotal);
            if ($discount <= 0) {
                return back()->withInput()->withErrors([
                    'voucher_code' => 'Voucher tidak memenuhi syarat (cek minimal pembelian / sisa kuota).',
                ]);
            }
        }

        $fee = 0;
        $total = max(0, $subtotal - $discount) + $fee;

        $firstVariant = $items->first()->variant;

        $order = DB::transaction(function () use ($items, $lineTotals, $user, $data, $subtotal, $discount, $fee, $total, $voucher, $firstVariant) {
            $order = Order::create([
                'order_code' => Order::generateOrderCode(),
                'user_id' => $user->id,
                // Order tetap simpan product_id/variant_id pertama sebagai
                // ringkasan (kompatibel dengan Filament resource & invoice
                // legacy yang membaca field ini).
                'product_id' => $firstVariant->product_id,
                'product_variant_id' => $firstVariant->id,
                'voucher_id' => $voucher?->id,
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'amount' => $subtotal,
                'discount_amount' => $discount,
                'fee' => $fee,
                'total_payment' => $total,
                'status' => Order::STATUS_PENDING,
                'source' => Order::SOURCE_WEB,
                'expired_at' => now()->addMinutes(
                    (int) config('pakasir.order_expiry_minutes', 60)
                ),
            ]);

            // Expand: untuk qty > 1 buat N OrderItem dengan qty=1 masing-masing,
            // supaya admin bisa input akun terpisah per pcs (1 pesanan 2 pcs =
            // butuh 2 akun terpisah, bukan 1 akun untuk dua-duanya).
            foreach ($items as $cart) {
                $info = $lineTotals[$cart->id];
                for ($i = 0; $i < $info['qty']; $i++) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cart->variant->product_id,
                        'product_variant_id' => $cart->variant->id,
                        'qty' => 1,
                        'unit_price' => $info['unit'],
                    ]);
                }
            }

            if ($voucher) {
                Voucher::where('id', $voucher->id)->increment('used_count');
            }

            // Kosongkan keranjang setelah Order berhasil dibuat. Kalau pembayaran
            // gagal user bisa add ulang dari product page.
            CartItem::where('user_id', $user->id)->delete();

            return $order;
        });

        Audit::log('order.created', $order, [
            'source' => 'cart',
            'items' => $items->count(),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
        ]);

        if (! $this->pakasir->isConfigured()) {
            return redirect()
                ->route('invoice.show', $order->order_code)
                ->with('warning', 'Payment gateway belum dikonfigurasi. Hubungi admin.');
        }

        $paymentUrl = $this->pakasir->buildPaymentUrl(
            $order,
            route('invoice.show', $order->order_code)
        );

        return redirect()->away($paymentUrl);
    }
}
