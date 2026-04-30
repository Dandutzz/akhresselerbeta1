<?php

namespace App\Models;

use App\Models\Concerns\BelongsToReseller;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use BelongsToReseller, HasFactory;

    protected $fillable = [
        'reseller_id',
        'name',
        'description',
        'terms_html',
        'short_description',
        'image',
        'price',
        'is_auto_send',
        'is_best_seller',
        'sold_count',
        'fake_sold_count',
        'category_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_auto_send' => 'boolean',
            'is_best_seller' => 'boolean',
            'sold_count' => 'integer',
            'fake_sold_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Total terjual yang ditampilkan ke publik. Bila fake_sold_enabled aktif,
     * tambahkan offset dari fake_sold_count. Penambahan dijaga agar tidak
     * pernah turun (real_sold hanya naik; admin hanya boleh menaikkan
     * fake_sold_count).
     */
    public function displaySoldCount(): int
    {
        $base = (int) $this->sold_count;
        $site = SiteSetting::current();
        if (($site->fake_sold_enabled ?? false)) {
            $base += (int) ($this->fake_sold_count ?? 0);
        }

        return max(0, $base);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }
        // Already a full URL?
        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }

    public function lowestPrice(): int
    {
        // Pakai relation collection kalau sudah di-eager-load (hindari N+1).
        // Fallback ke query DB hanya kalau variants belum dimuat.
        if ($this->relationLoaded('variants')) {
            return (int) ($this->variants->min('price') ?? $this->price);
        }

        return (int) ($this->variants()->min('price') ?? $this->price);
    }
}
