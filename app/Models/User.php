<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // is_admin & balance SENGAJA tidak dimasukkan ke $fillable untuk mencegah
    // mass-assignment privilege escalation. is_admin di-set lewat seeder /
    // command / UserResource (eksplisit). balance HARUS via WalletService
    // (atomic + audit trail).
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
        ];
    }

    /**
     * Hanya user dengan flag is_admin = true (dan TIDAK banned) yang boleh
     * akses panel Filament. Kalau admin di-ban, juga gak bisa masuk.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin && ! $this->is_banned;
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
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
        return Order::whereNull('user_id')
            ->where('customer_email', $this->email)
            ->update(['user_id' => $this->id]);
    }
}
