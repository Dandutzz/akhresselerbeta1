<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'store_name',
        'tagline',
        'logo_path',
        'brand_color',
        'accent_color',
        'hero_title',
        'hero_subtitle',
        'contact_email',
        'wa_number',
        'wa_default_message',
        'instagram_url',
        'tiktok_url',
        'telegram_url',
        'facebook_url',
        'whatsapp_channel_url',
        'how_to_order_html',
        'terms_html',
        'about_html',
        'footer_about',
        'support_hours',
        'fonnte_api_key',
        'fonnte_auto_send_credentials',
        'fonnte_credentials_template',
        'fonnte_admin_number',
        'fonnte_webhook_secret',
        'fake_sold_enabled',
        'floating_notif_enabled',
        'floating_notif_use_real',
        'floating_notif_use_fake',
        'floating_notif_interval_min',
        'floating_notif_interval_max',
    ];

    /** Fonnte API key disimpan terenkripsi (AES-256-CBC) — sensitive credential. */
    protected function casts(): array
    {
        return [
            'fonnte_api_key' => 'encrypted',
            'fonnte_auto_send_credentials' => 'boolean',
            'fake_sold_enabled' => 'boolean',
            'floating_notif_enabled' => 'boolean',
            'floating_notif_use_real' => 'boolean',
            'floating_notif_use_fake' => 'boolean',
            'floating_notif_interval_min' => 'integer',
            'floating_notif_interval_max' => 'integer',
        ];
    }

    /** Cache per-request supaya SiteSetting hanya di-query 1x. */
    protected static ?self $instance = null;

    /** Singleton pattern: ambil row pertama, atau buat default. */
    public static function current(): self
    {
        return self::$instance ??= static::firstOrCreate(['id' => 1], [
            'store_name' => config('app.name', 'Akhpremium Store'),
        ]);
    }

    /** Reset cache (dipanggil otomatis saat row ter-update). */
    public static function clearCache(): void
    {
        self::$instance = null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }

    public function waLink(?string $message = null): ?string
    {
        if (! $this->wa_number) {
            return null;
        }
        $msg = $message ?? $this->wa_default_message ?? 'Halo admin, saya butuh bantuan.';

        return 'https://wa.me/'.$this->wa_number.'?text='.rawurlencode($msg);
    }
}
