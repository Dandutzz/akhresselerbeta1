<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_RESELLER = 'reseller';

    public const ROLE_CUSTOMER = 'customer';

    // is_admin & balance SENGAJA tidak dimasukkan ke $fillable untuk mencegah
    // mass-assignment privilege escalation. is_admin di-set lewat seeder /
    // command / UserResource (eksplisit). balance HARUS via WalletService
    // (atomic + audit trail). role bisa diisi via Filament panel (admin
    // form & reseller register handler) — bukan endpoint publik.
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_banned',
        'banned_at',
        'ban_reason',
        'last_login_at',
        'telegram_chat_id',
        'telegram_username',
        'role',
        'reseller_slug',
        'approved_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_banned' => 'boolean',
            'banned_at' => 'datetime',
            'balance' => 'integer',
            'last_login_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || (bool) $this->is_admin;
    }

    public function isReseller(): bool
    {
        return $this->role === self::ROLE_RESELLER;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    /**
     * Reseller sudah disetujui admin untuk login ke panel reseller.
     * Selama belum approved, login boleh tapi panel reseller akan menolak.
     */
    public function isApprovedReseller(): bool
    {
        return $this->isReseller() && ! is_null($this->approved_at) && ! $this->is_banned;
    }

    /**
     * Akses panel multi-tenant:
     *  - panel admin → admin only (tidak banned)
     *  - panel reseller → reseller only (sudah approved + tidak banned)
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->is_banned) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->isAdmin(),
            'reseller' => $this->isApprovedReseller(),
            default => false,
        };
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function resellerSettings(): HasOne
    {
        return $this->hasOne(ResellerSetting::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(ResellerSubscription::class);
    }

    /** Produk yang dimiliki sebagai reseller (bukan order yang dia beli). */
    public function ownedProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'reseller_id');
    }

    /** Order masuk ke reseller ini (revenue). */
    public function ownedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'reseller_id');
    }

    /**
     * Total spend = jumlah amount dari order PAID milik user. Cached per
     * request supaya gak query berulang di table list.
     */
    public function totalSpend(): int
    {
        return (int) $this->orders()->where('status', 'paid')->sum('amount');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function telegramLinkTokens(): HasMany
    {
        return $this->hasMany(TelegramLinkToken::class);
    }

    /**
     * Klaim semua order tamu (user_id null) yang dipakai dengan email yang sama.
     * Dipanggil saat user baru register atau login pertama kali untuk
     * menggabungkan history pesanan guest sebelumnya.
     *
     * @return int jumlah order yang berhasil di-klaim
     */
    public function linkGuestOrders(): int
    {
        return Order::withoutResellerScope()
            ->whereNull('user_id')
            ->where('customer_email', $this->email)
            ->update(['user_id' => $this->id]);
    }
}
