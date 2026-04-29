<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Support\Audit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wrapper API Fonnte (https://fonnte.com) untuk auto-kirim pesan WA ke customer.
 * - Token diambil dari SiteSetting.fonnte_api_key (encrypted).
 * - Kalau token kosong / toggle off, kirim di-skip diam-diam (graceful).
 * - Setiap pengiriman ter-audit (sukses/gagal) supaya admin bisa trace.
 */
class FonnteWhatsApp
{
    public const ENDPOINT = 'https://api.fonnte.com/send';

    public const DEFAULT_TEMPLATE = "Halo, terima kasih sudah berbelanja di kami!\n\n".
        "Berikut detail akun untuk order *{{order_code}}*:\n".
        "Produk: {{product}}\n".
        "Paket: {{variant}}\n".
        "Email/No HP: {{email}}\n".
        "Password: {{password}}\n".
        "Info Tambahan: {{additional_info}}\n\n".
        'Harap simpan kredensial ini dan jangan dibagikan ke siapapun. '.
        'Jika ada kendala silakan balas pesan ini.';

    public const ADMIN_TEMPLATE = "[Order Baru PAID]\n".
        "Kode: {{order_code}}\n".
        "Produk: {{product}} - {{variant}}\n".
        "Pembeli: {{customer_email}} / {{customer_phone}}\n".
        "Akun terkirim: {{email}}\n";

    /**
     * Kirim kredensial akun untuk satu order ke nomor customer.
     * Return true jika request berhasil dikirim ke Fonnte (status 200 + status response = success).
     * Return false untuk semua kasus skip/gagal (toggle off, no token, no phone, no stock, http error).
     */
    public function sendCredentials(Order $order): bool
    {
        $site = SiteSetting::current();

        if (! $site->fonnte_auto_send_credentials || empty($site->fonnte_api_key)) {
            return false;
        }

        $phone = $this->normalizePhone((string) ($order->customer_phone ?? ''));
        if ($phone === '') {
            return false;
        }

        $template = trim((string) ($site->fonnte_credentials_template ?? '')) ?: self::DEFAULT_TEMPLATE;

        // Path multi-item: kirim 1 pesan per item (variant) yang berhasil di-assign stok.
        $items = $order->items()->with(['stock', 'product', 'variant'])->get();
        $itemsWithStock = $items->filter(fn ($i) => $i->stock !== null);
        if ($itemsWithStock->isNotEmpty()) {
            $sentAny = false;
            $lastStock = null;
            foreach ($itemsWithStock as $item) {
                $message = strtr($template, [
                    '{{order_code}}' => (string) $order->order_code,
                    '{{product}}' => (string) optional($item->product)->name,
                    '{{variant}}' => (string) optional($item->variant)->name,
                    '{{email}}' => (string) $item->stock->email_or_phone,
                    '{{password}}' => (string) $item->stock->password,
                    '{{additional_info}}' => (string) ($item->stock->additional_info ?? '-'),
                ]);
                if ($this->send($order, $phone, $message)) {
                    $sentAny = true;
                    $lastStock = $item->stock;
                }
            }
            if ($sentAny && $lastStock) {
                $this->notifyAdmin($order, $lastStock);
            }

            return $sentAny;
        }

        // Fallback legacy: order single-stock pakai Order.stock_id.
        $stock = $order->stock;
        if (! $stock) {
            return false;
        }

        $message = strtr($template, [
            '{{order_code}}' => (string) $order->order_code,
            '{{product}}' => (string) optional($order->product)->name,
            '{{variant}}' => (string) optional($order->variant)->name,
            '{{email}}' => (string) $stock->email_or_phone,
            '{{password}}' => (string) $stock->password,
            '{{additional_info}}' => (string) ($stock->additional_info ?? '-'),
        ]);

        $sent = $this->send($order, $phone, $message);
        if ($sent) {
            $this->notifyAdmin($order, $stock);
        }

        return $sent;
    }

    /** Kirim notif ringkas ke admin number kalau di-set di Site Settings. */
    public function notifyAdmin(Order $order, $stock = null): bool
    {
        $site = SiteSetting::current();
        $adminPhone = $this->normalizePhone((string) ($site->fonnte_admin_number ?? ''));
        if ($adminPhone === '' || empty($site->fonnte_api_key)) {
            return false;
        }

        $stock ??= $order->stock;
        $message = strtr(self::ADMIN_TEMPLATE, [
            '{{order_code}}' => (string) $order->order_code,
            '{{product}}' => (string) optional($order->product)->name,
            '{{variant}}' => (string) optional($order->variant)->name,
            '{{customer_email}}' => (string) ($order->customer_email ?? '-'),
            '{{customer_phone}}' => (string) ($order->customer_phone ?? '-'),
            '{{email}}' => (string) optional($stock)->email_or_phone ?: '-',
        ]);

        return $this->send($order, $adminPhone, $message, isAdmin: true);
    }

    /** Kirim arbitrary message ke nomor (helper internal + dipakai oleh webhook untuk reply). */
    public function send(?Order $order, string $phone, string $message, bool $isAdmin = false): bool
    {
        $site = SiteSetting::current();
        if (empty($site->fonnte_api_key)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->withHeaders(['Authorization' => $site->fonnte_api_key])
                ->timeout(15)
                ->post(self::ENDPOINT, [
                    'target' => $phone,
                    'message' => $message,
                    'countryCode' => '62',
                ]);

            $body = $response->json() ?? [];
            $ok = $response->successful() && (($body['status'] ?? false) === true);

            Audit::log($ok ? 'whatsapp.sent' : 'whatsapp.failed', $order, [
                'channel' => 'fonnte',
                'phone' => $phone,
                'is_admin' => $isAdmin,
                'http_status' => $response->status(),
                'response' => $body,
            ]);

            return $ok;
        } catch (Throwable $e) {
            Log::error('Fonnte send failed', ['order_id' => $order?->id, 'error' => $e->getMessage()]);
            Audit::log('whatsapp.failed', $order, [
                'channel' => 'fonnte',
                'phone' => $phone,
                'is_admin' => $isAdmin,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** Normalisasi: 0xxx → 62xxx, +62xxx → 62xxx, kosongkan karakter selain digit. */
    public function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/[^0-9]/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }
        if ($digits[0] === '0') {
            return '62'.substr($digits, 1);
        }

        return $digits;
    }
}
