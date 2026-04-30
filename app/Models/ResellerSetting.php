<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Konfigurasi per-reseller — branding, kredensial Pakasir, token bot Telegram,
 * Fonnte, dll. Field sensitif (token / API key) dienkripsi pakai APP_KEY via
 * `encrypted` cast (AES-256-CBC).
 */
class ResellerSetting extends Model
{
    protected $fillable = [
        'user_id',
        'store_name',
        'tagline',
        'logo_path',
        'brand_color',
        'contact_email',
        'wa_number',
        'pakasir_project',
        'pakasir_api_key',
        'pakasir_qris_only',
        'pakasir_order_expiry_minutes',
        'tg_bot_token',
        'tg_bot_username',
        'tg_webhook_secret',
        'tg_notif_bot_token',
        'tg_admin_chat_id',
        'fonnte_api_key',
        'fonnte_admin_number',
        'fonnte_auto_send_credentials',
        'is_active',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'pakasir_api_key' => 'encrypted',
            'pakasir_qris_only' => 'boolean',
            'pakasir_order_expiry_minutes' => 'integer',
            'tg_bot_token' => 'encrypted',
            'tg_notif_bot_token' => 'encrypted',
            'fonnte_api_key' => 'encrypted',
            'fonnte_auto_send_credentials' => 'boolean',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate webhook secret unik 48-char untuk routing Telegram per
     * reseller. Dipanggil saat reseller pertama kali aktivasi bot.
     */
    public static function generateWebhookSecret(): string
    {
        do {
            $secret = Str::random(48);
        } while (self::query()->where('tg_webhook_secret', $secret)->exists());

        return $secret;
    }
}
