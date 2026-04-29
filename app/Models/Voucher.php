<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'min_purchase',
        'max_discount',
        'usage_limit',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_purchase' => 'integer',
            'max_discount' => 'integer',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $q): Builder
    {
        $now = now();

        return $q->where('is_active', true)
            ->where(fn ($s) => $s->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($s) => $s->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
    }

    public function isAvailable(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $now = now();
        if ($this->starts_at && $this->starts_at->gt($now)) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->lt($now)) {
            return false;
        }
        if ($this->usage_limit > 0 && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Hitung diskon untuk total tertentu. Mengembalikan rupiah (integer, >=0).
     * Jika voucher tidak available atau tidak memenuhi min_purchase, return 0.
     */
    public function discountFor(int $amount): int
    {
        if (! $this->isAvailable()) {
            return 0;
        }
        if ($this->min_purchase > 0 && $amount < $this->min_purchase) {
            return 0;
        }

        $discount = match ($this->type) {
            self::TYPE_PERCENT => (int) floor($amount * min(100, max(0, $this->value)) / 100),
            self::TYPE_FIXED => (int) $this->value,
            default => 0,
        };

        if ($this->max_discount > 0 && $discount > $this->max_discount) {
            $discount = (int) $this->max_discount;
        }

        // Jangan diskon melebihi total
        return (int) max(0, min($discount, $amount));
    }
}
