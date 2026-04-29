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

    // is_admin SENGAJA tidak dimasukkan ke $fillable untuk mencegah mass-assignment
    // privilege escalation. Set role admin hanya lewat: $user->is_admin = true;
    // $user->save() di tempat yang sudah dilindungi (mis. seeder, command artisan,
    // resource Filament UserResource khusus admin).
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
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
        ];
    }

    /**
     * Hanya user dengan flag is_admin = true yang boleh akses panel Filament.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin;
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
