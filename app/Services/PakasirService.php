<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper tipis untuk Pakasir Payment Gateway.
 *
 * Referensi: https://pakasir.com/p/docs
 */
class PakasirService
{
    public function __construct(
        protected ?string $project = null,
        protected ?string $apiKey = null,
        protected ?string $baseUrl = null,
    ) {
        $this->project ??= config('pakasir.project');
        $this->apiKey ??= config('pakasir.api_key');
        $this->baseUrl ??= rtrim((string) config('pakasir.base_url'), '/');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->project) && ! empty($this->apiKey);
    }

    /**
     * Bangun URL halaman pembayaran Pakasir untuk satu Order.
     */
    public function buildPaymentUrl(Order $order, ?string $redirectUrl = null): string
    {
        $url = sprintf(
            '%s/pay/%s/%d?order_id=%s',
            $this->baseUrl,
            rawurlencode((string) $this->project),
            (int) $order->total_payment,
            rawurlencode($order->order_code),
        );

        if ($redirectUrl) {
            $url .= '&redirect='.rawurlencode($redirectUrl);
        }

        if (config('pakasir.qris_only')) {
            $url .= '&qris_only=1';
        }

        return $url;
    }

    /**
     * Buat transaksi QRIS via Pakasir API & dapatkan QR string (EMVCo) untuk
     * dirender sebagai gambar QR di sisi client. QR string disimpan di
     * Order::payment_qr_string supaya tidak perlu hit API berulang.
     *
     * Docs: POST https://app.pakasir.com/api/transactioncreate/qris
     *
     * @return array{payment_number:string,total_payment:int,fee:int,expired_at:?string}|null
     */
    public function createQrisTransaction(Order $order): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        // Sudah pernah di-fetch sebelumnya — pakai cache di Order.
        if (! empty($order->payment_qr_string)) {
            return [
                'payment_number' => $order->payment_qr_string,
                'total_payment' => (int) $order->total_payment,
                'fee' => (int) ($order->fee ?? 0),
                'expired_at' => optional($order->expired_at)->toIso8601String(),
            ];
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl.'/api/transactioncreate/qris', [
                    'project' => $this->project,
                    'order_id' => $order->order_code,
                    'amount' => (int) $order->total_payment,
                    'api_key' => $this->apiKey,
                ]);
        } catch (\Throwable $e) {
            Log::error('Pakasir transactioncreate/qris error', [
                'order_code' => $order->order_code,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Pakasir transactioncreate/qris non-success', [
                'order_code' => $order->order_code,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $payment = $response->json('payment');
        if (! is_array($payment) || empty($payment['payment_number'])) {
            return null;
        }

        $order->forceFill([
            'payment_qr_string' => $payment['payment_number'],
            'payment_method_requested' => 'qris',
        ])->save();

        return [
            'payment_number' => (string) $payment['payment_number'],
            'total_payment' => (int) ($payment['total_payment'] ?? $order->total_payment),
            'fee' => (int) ($payment['fee'] ?? 0),
            'expired_at' => $payment['expired_at'] ?? null,
        ];
    }

    /**
     * Panggil Transaction Detail API untuk mem-verifikasi status sebuah transaksi.
     * Dokumentasi Pakasir menganjurkan verifikasi ulang via API ini, TIDAK hanya
     * percaya pada payload webhook.
     *
     * @return array<string,mixed>|null Array detail transaksi dari Pakasir,
     *                                  atau null jika gagal.
     */
    public function fetchTransactionDetail(string $orderCode, int $amount): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->get($this->baseUrl.'/api/transactiondetail', [
                    'project' => $this->project,
                    'amount' => $amount,
                    'order_id' => $orderCode,
                    'api_key' => $this->apiKey,
                ]);
        } catch (\Throwable $e) {
            Log::error('Pakasir transactiondetail error', [
                'order_code' => $orderCode,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Pakasir transactiondetail non-success', [
                'order_code' => $orderCode,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $json = $response->json();

        return is_array($json) && isset($json['transaction']) && is_array($json['transaction'])
            ? $json['transaction']
            : null;
    }

    /**
     * Validasi payload webhook terhadap Order lokal + verifikasi via API.
     * Kembalikan true hanya bila:
     *  - amount di payload == Order.total_payment
     *  - status di API Pakasir == 'completed'
     */
    public function verifyWebhook(Order $order, array $payload): bool
    {
        $amount = (int) ($payload['amount'] ?? 0);
        if ($amount !== (int) $order->total_payment) {
            return false;
        }

        $detail = $this->fetchTransactionDetail($order->order_code, (int) $order->total_payment);
        if (! $detail) {
            return false;
        }

        return ($detail['status'] ?? null) === 'completed';
    }
}
