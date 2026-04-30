<?php

namespace App\Models;

use App\Models\Concerns\BelongsToReseller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flashsale extends Model
{
    use BelongsToReseller;

    protected $fillable = [
        'reseller_id',
        'name',
        'product_variant_id',
        'flash_price',
        'quota',
        'sold',
        'start_at',
        'end_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('start_at', '<=', now())
            ->where('end_at', '>=', now())
            ->whereColumn('sold', '<', 'quota');
    }

    public function isActiveNow(): bool
    {
        return $this->is_active
            && $this->start_at?->isPast()
            && $this->end_at?->isFuture()
            && $this->sold < $this->quota;
    }

    public function discountPercent(): int
    {
        $original = $this->variant?->price ?? 0;
        if (! $original) {
            return 0;
        }

        return (int) round((($original - $this->flash_price) / $original) * 100);
    }

    public function remaining(): int
    {
        return max(0, $this->quota - $this->sold);
    }
}
