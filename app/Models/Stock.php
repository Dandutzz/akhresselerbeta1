<?php

namespace App\Models;

use App\Models\Concerns\BelongsToReseller;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use BelongsToReseller, HasFactory;

    protected $fillable = [
        'reseller_id',
        'product_variant_id',
        'email_or_phone',
        'password',
        'additional_info',
        'is_sold',
        'sold_at',
    ];

    /**
     * Kredensial akun disimpan terenkripsi (AES-256-CBC) pakai APP_KEY.
     * Kalau APP_KEY bocor/ganti, data tidak bisa didekripsi, jadi jaga baik-baik.
     */
    protected function casts(): array
    {
        return [
            'email_or_phone' => 'encrypted',
            'password' => 'encrypted',
            'additional_info' => 'encrypted',
            'is_sold' => 'boolean',
            'sold_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
