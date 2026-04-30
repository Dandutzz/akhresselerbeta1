<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderFulfillment;
use App\Services\PakasirService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(protected PakasirService $pakasir) {}

    /**
     * GET /invoice/{order_code}
     * Halaman invoice publik — URL tidak dapat ditebak (order_code random).
     */
    public function show(Request $request, string $orderCode): View
    {
        $order = Order::with(['product', 'variant', 'stock', 'items.product', 'items.variant', 'items.stock'])
            ->where('order_code', $orderCode)
            ->firstOrFail();

        // Fallback: jika user balik dari Pakasir dan status lokal masih pending,
        // coba sinkron dari Pakasir secara on-demand (tanpa menunggu webhook).
        if ($order->isPending() && $this->pakasir->isConfigured()) {
            $detail = $this->pakasir->fetchTransactionDetail(
                $order->order_code,
                (int) $order->total_payment
            );

            if ($detail && ($detail['status'] ?? null) === 'completed') {
                /** @var OrderFulfillment $fulfillment */
                $fulfillment = app(OrderFulfillment::class);
                $fulfillment->markPaidAndAssignStock($order, [
                    'amount' => $detail['amount'] ?? $order->total_payment,
                    'payment_method' => $detail['payment_method'] ?? null,
                    'source' => 'invoice_poll',
                ]);
                $order->refresh();
            }
        }

        // Fetch QR string buat di-render sebagai gambar di view (hanya saat
        // status masih pending — kalau sudah PAID, QR tidak relevan lagi).
        $qris = null;
        if ($order->isPending() && $this->pakasir->isConfigured()) {
            $qris = $this->pakasir->createQrisTransaction($order);
        }

        return view('invoice', [
            'order' => $order,
            'credentials' => $this->decryptedCredentials($order),
            'itemCredentials' => $this->itemCredentials($order),
            'qris' => $qris,
        ]);
    }

    /**
     * Ekstrak kredensial akun dari stock yang sudah di-assign.
     * Laravel otomatis men-decrypt karena Stock punya cast 'encrypted'.
     *
     * @return array{email_or_phone:?string,password:?string,additional_info:?string}|null
     */
    protected function decryptedCredentials(Order $order): ?array
    {
        if (! $order->isPaid() || ! $order->stock) {
            return null;
        }

        return [
            'email_or_phone' => $order->stock->email_or_phone,
            'password' => $order->stock->password,
            'additional_info' => $order->stock->additional_info,
        ];
    }

    /**
     * Untuk order multi-item: ekstrak kredensial per OrderItem agar invoice bisa
     * tampilkan SEMUA akun yang sudah ter-assign (termasuk produk + variant
     * masing-masing).
     *
     * @return array<int, array{product:string, variant:string, qty:int, line_total:int, email_or_phone:?string, password:?string, additional_info:?string, delivered:bool}>
     */
    protected function itemCredentials(Order $order): array
    {
        if (! $order->isPaid() || $order->items->isEmpty()) {
            return [];
        }

        return $order->items->map(function ($item) {
            return [
                'product' => $item->product?->name ?? '—',
                'variant' => $item->variant?->name ?? '—',
                'qty' => max(1, (int) $item->qty),
                'line_total' => $item->lineTotal(),
                'email_or_phone' => $item->stock?->email_or_phone,
                'password' => $item->stock?->password,
                'additional_info' => $item->stock?->additional_info,
                'delivered' => $item->stock !== null,
            ];
        })->all();
    }
}
