<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramLinkToken extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'token', 'expires_at', 'used_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    /** Generate token unik 6-digit alfanumerik upper-case. */
    public static function generateFor(User $user, int $ttlMinutes = 15): self
    {
        // Invalidasi token aktif sebelumnya untuk user ini.
        self::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()->subSecond()]);

        do {
            $token = strtoupper(substr(bin2hex(random_bytes(8)), 0, 6));
        } while (self::where('token', $token)->exists());

        return self::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }
}
