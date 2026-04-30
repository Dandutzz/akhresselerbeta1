<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Voucher;
use App\Services\PakasirService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(protected PakasirService $pakasir) {}

    /**
     * GET /checkout/{product}/{variant}
     * Halaman form checkout instan (tanpa login).
     */
    public function show(Product $product, ProductVariant $variant): View|RedirectResponse
    {
        abort_if($variant->product_id !== $product->id, 404);

        $available = $variant->availableStocks()->count();

        // Hanya block kalau VARIAN ini set auto-send dan stoknya nol.
        // Varian manual boleh tetap di-checkout walau stocknya kosong —
        // admin akan input akun manual setelah PAID.
        if ($available <= 0 && $variant->isAutoSend()) {
            return redirect()
                ->route('products.show', $product)
                ->with('error', 'Mohon maaf, stok untuk varian ini sedang kosong.');
        }

        return view('checkout', [
            'product' => $product,
            'variant' => $variant,
            'available' => $available,
        ]);
    }

    /**
     * POST /checkout
     * Validasi input, kunci harga di server, buat Order, redirect ke Pakasir.
     */
    public function store(Request $request): RedirectResponse
    {
        // Tolak user banned (defensif — login juga sudah block, tapi kalau sesi
        // masih hidup saat di-ban, harus tetap di-block di sini).
        if (auth()->check() && auth()->user()->is_banned) {
            auth()->logout();

            return redirect()->route('login')->with('error', 'Akun Anda di-banned. Tidak bisa checkout.');
        }

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\- ]+$/'],
            'voucher_code' => ['nullable', 'string', 'max:64'],
        ]);

        /** @var ProductVariant $variant */
        $variant = ProductVariant::with('product')->findOrFail($data['product_variant_id']);

        abort_if(
            $variant->product_id !== (int) $data['product_id'],
            422,
            'Varian tidak cocok dengan produk.'
        );

        // Hitung harga ULANG di server — jangan percaya input client.
        // Pakai harga flashsale kalau sedang aktif untuk varian ini.
        $amount = $variant->effectivePrice();
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
            $discount = $voucher->discountFor($amount);
            if ($discount <= 0) {
                return back()->withInput()->withErrors([
                    'voucher_code' => 'Voucher tidak memenuhi syarat (cek minimal pembelian / sisa kuota).',
                ]);
            }
        }

        $fee = 0;
        $total = max(0, $amount - $discount) + $fee;
        $userId = Auth::id();

        $order = DB::transaction(function () use ($variant, $data, $amount, $discount, $fee, $total, $userId, $voucher) {
            $order = Order::create([
                'order_code' => Order::generateOrderCode(),
                'user_id' => $userId, // null untuk guest
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'voucher_id' => $voucher?->id,
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'amount' => $amount,
                'discount_amount' => $discount,
                'fee' => $fee,
                'total_payment' => $total,
                'status' => Order::STATUS_PENDING,
                'source' => Order::SOURCE_WEB,
                'expired_at' => now()->addMinutes(
                    PakasirService::orderExpiryMinutes()
                ),
            ]);

            // Sinkron OrderItem (unified fulfillment path) — single-item juga
            // punya 1 baris OrderItem agar service fulfillment konsisten antara
            // checkout instan dan checkout cart multi-item.
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'qty' => 1,
                'unit_price' => $amount,
            ]);

            if ($voucher) {
                Voucher::where('id', $voucher->id)->increment('used_count');
            }

            return $order;
        });

        Audit::log('order.created', $order, [
            'amount' => $amount,
            'variant' => $variant->name,
            'product' => $variant->product?->name,
        ]);

        // Redirect ke invoice publik kita sendiri — QRIS akan di-render di
        // halaman tersebut via PakasirService::createQrisTransaction(). User
        // tidak perlu redirect ke halaman hosted Pakasir.
        return redirect()
            ->route('invoice.show', $order->order_code)
            ->with('success', 'Order berhasil dibuat. Scan QRIS di bawah untuk membayar.');
    }
}
