<?php

namespace App\Models;

use App\Models\Concerns\BelongsToReseller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use BelongsToReseller;

    public const SHARE_SHARING = 'sharing';

    public const SHARE_PRIVATE = 'private';

    public const SHARE_SHARING_ANTILIMIT = 'sharing_antilimit';

    protected $fillable = [
        'reseller_id',
        'product_id',
        'name',
        'price',
        'is_auto_send',
        'warranty_days',
        'share_type',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_auto_send' => 'boolean',
            'warranty_days' => 'integer',
        ];
    }

    public function shareTypeLabel(): ?string
    {
        return match ($this->share_type) {
            self::SHARE_SHARING => 'Sharing',
            self::SHARE_PRIVATE => 'Private',
            self::SHARE_SHARING_ANTILIMIT => 'Sharing Antilimit',
            default => null,
        };
    }

    /**
     * Auto-delivery dipakai per-varian. Kalau kolom is_auto_send di varian
     * di-set (true/false), pakai itu. Kalau NULL, fallback ke setting product.
     */
    public function isAutoSend(): bool
    {
        if ($this->is_auto_send !== null) {
            return (bool) $this->is_auto_send;
        }

        return (bool) ($this->product?->is_auto_send ?? false);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function availableStocks(): HasMany
    {
        return $this->hasMany(Stock::class)->where('is_sold', false);
    }

    public function flashsales(): HasMany
    {
        return $this->hasMany(Flashsale::class);
    }

    /**
     * Relasi yang HANYA berisi flashsale aktif (untuk eager-loading di card homepage).
     * Pakai scope yang sama dengan Flashsale::active() supaya logic konsisten.
     */
    public function activeFlashsales(): HasMany
    {
        return $this->hasMany(Flashsale::class)->active();
    }

    public function activeFlashsale(): ?Flashsale
    {
        // Kalau activeFlashsales sudah di-eager-load, pakai collection-nya.
        if ($this->relationLoaded('activeFlashsales')) {
            return $this->activeFlashsales->first();
        }

        return Flashsale::active()->where('product_variant_id', $this->id)->first();
    }

    /** Harga efektif: pakai flashsale price kalau ada, jika tidak harga normal. */
    public function effectivePrice(): int
    {
        $fs = $this->activeFlashsale();

        return $fs ? (int) $fs->flash_price : (int) $this->price;
    }
}
