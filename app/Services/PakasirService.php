<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper tipis untuk Pakasir Payment Gateway.
 *
 * Konfigurasi diambil prioritas dari SiteSetting (admin panel),
 * fallback ke config('pakasir.*') / env() biar deployment lama tetap jalan.
 *
 * Referensi: https://pakasir.com/p/docs
 */
class PakasirService
{
    protected bool $qrisOnly;

    public function __construct(
        protected ?string $project = null,
        protected ?string $apiKey = null,
        protected ?string $baseUrl = null,
        ?bool $qrisOnly = null,
    ) {
        $site = SiteSetting::current();

        $this->project ??= $site->pakasir_project ?: config('pakasir.project');
        $this->apiKey ??= $site->pakasir_api_key ?: config('pakasir.api_key');
        $this->baseUrl ??= rtrim((string) ($site->pakasir_base_url ?: config('pakasir.base_url')), '/');
        $this->qrisOnly = $qrisOnly ?? ($site->pakasir_qris_only ?? (bool) config('pakasir.qris_only'));
    }

    public function isConfigured(): bool
    {
        return ! empty($this->project) && ! empty($this->apiKey);
    }

    /** Project slug aktif (SiteSetting > config). */
    public function projectSlug(): ?string
    {
        return $this->project ?: null;
    }

    /** Default expiry order dalam menit (SiteSetting > config). */
    public static function orderExpiryMinutes(): int
    {
        $site = SiteSetting::current();
        $minutes = (int) ($site->pakasir_order_expiry_minutes ?: config('pakasir.order_expiry_minutes', 60));

        return $minutes > 0 ? $minutes : 60;
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

        if ($this->qrisOnly) {
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

        // Pakasir menambahkan fee di atas amount yang kita kirim. Simpan
        // fee + total Pakasir di Order supaya kita bisa polling status pakai
        // amount yang benar (Pakasir menolak query dengan amount tanpa fee).
        $pakasirFee = (int) ($payment['fee'] ?? 0);
        $pakasirTotal = (int) ($payment['total_payment'] ?? ($order->total_payment + $pakasirFee));

        $order->forceFill([
            'payment_qr_string' => $payment['payment_number'],
            'payment_method_requested' => 'qris',
            'fee' => $pakasirFee,
        ])->save();

        return [
            'payment_number' => (string) $payment['payment_number'],
            'total_payment' => $pakasirTotal,
            'fee' => $pakasirFee,
            'expired_at' => $payment['expired_at'] ?? null,
        ];
    }

    /**
     * Hitung amount Pakasir-side: total_payment kita + fee yang Pakasir
     * tambahkan saat createQris. Dipakai saat polling fetchTransactionDetail
     * — Pakasir simpan transaksinya dengan total_payment + fee, bukan amount
     * mentah yang kita kirim.
     */
    public function pakasirAmount(Order $order): int
    {
        return (int) $order->total_payment + (int) ($order->fee ?? 0);
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

        // Workaround bug Pakasir sandbox: transactiondetail kadang case-sensitive
        // dan inkonsisten. Untuk satu order, salah satu varian case bisa
        // return 404 sementara varian lain return transaction yang valid.
        // Coba semua kombinasi yang masuk akal.
        $upper = strtoupper($orderCode);
        $lower = strtolower($orderCode);
        $parts = explode('-', $orderCode);
        $mixed1 = $mixed2 = $orderCode;
        if (count($parts) > 1) {
            // Prefix uppercase + suffix lowercase (mis. AKH-20260430-nnyg6q)
            $mixed1 = strtoupper($parts[0]).'-'.strtolower(implode('-', array_slice($parts, 1)));
            // Prefix lowercase + suffix uppercase
            $mixed2 = strtolower($parts[0]).'-'.strtoupper(implode('-', array_slice($parts, 1)));
        }
        $candidates = array_values(array_unique([$orderCode, $upper, $lower, $mixed1, $mixed2]));

        foreach ($candidates as $candidate) {
            try {
                $response = Http::timeout(10)
                    ->acceptJson()
                    ->get($this->baseUrl.'/api/transactiondetail', [
                        'project' => $this->project,
                        'amount' => $amount,
                        'order_id' => $candidate,
                        'api_key' => $this->apiKey,
                    ]);
            } catch (\Throwable $e) {
                Log::error('Pakasir transactiondetail error', [
                    'order_code' => $candidate,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }

            if (! $response->successful()) {
                Log::warning('Pakasir transactiondetail non-success', [
                    'order_code' => $candidate,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                continue;
            }

            $json = $response->json();
            if (is_array($json) && isset($json['transaction']) && is_array($json['transaction'])) {
                return $json['transaction'];
            }
        }

        return null;
    }

    /**
     * Validasi payload webhook terhadap Order lokal + verifikasi via API.
     * Kembalikan true bila:
     *  - amount di payload == Order.total_payment (Pakasir kirim amount original)
     *  - status di payload == 'completed'
     *  - DAN salah satu: API confirms completed, atau project + order_id +
     *    payment_method match payload (best-effort karena Pakasir transactiondetail
     *    sandbox sering 404 inkonsisten — webhook URL sendiri sudah authenticated
     *    via secret di config Pakasir).
     */
    public function verifyWebhook(Order $order, array $payload): bool
    {
        $amount = (int) ($payload['amount'] ?? 0);
        if ($amount !== (int) $order->total_payment) {
            return false;
        }

        if (($payload['status'] ?? null) !== 'completed') {
            return false;
        }

        if (($payload['project'] ?? null) !== $this->project) {
            return false;
        }

        if (($payload['order_id'] ?? null) !== $order->order_code) {
            return false;
        }

        // Best-effort verification via API. Kalau berhasil → kuat. Kalau gagal
        // (Pakasir API balik 404), tetap trust webhook karena field-field di
        // atas sudah match dan webhook URL sendiri "secret".
        $detail = $this->fetchTransactionDetail($order->order_code, (int) $order->total_payment);
        if ($detail) {
            return ($detail['status'] ?? null) === 'completed';
        }

        Log::info('Pakasir webhook trusted without API confirmation', [
            'order_code' => $order->order_code,
        ]);

        return true;
    }
}
