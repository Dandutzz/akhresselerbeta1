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
use Illuminate\Support\Str;
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
                'order_code' => $this->generateOrderCode(),
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
                'expired_at' => now()->addMinutes(
                    (int) config('pakasir.order_expiry_minutes', 60)
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

    protected function generateOrderCode(): string
    {
        // Contoh: AKH-20260427-AB12CD. strtoupper menyusutkan charset Str::random
        // jadi 36 (A-Z + 0-9) — collision sangat tidak mungkin tapi mungkin.
        // Retry 5x baru fallback ke 10 char (search space jauh lebih besar)
        // supaya checkout user tidak 500 kalau kebetulan tabrakan.
        for ($i = 0; $i < 5; $i++) {
            $code = 'AKH-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
            if (! Order::where('order_code', $code)->exists()) {
                return $code;
            }
        }

        // Fallback 10-char tetap loop sampai unik supaya gak ada celah 500
        // dari unique constraint violation, walau probabilitas hampir 0.
        do {
            $fallback = 'AKH-'.now()->format('Ymd').'-'.strtoupper(Str::random(10));
        } while (Order::where('order_code', $fallback)->exists());

        return $fallback;
    }
}
