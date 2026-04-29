<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_code',
        'user_id',
        'product_id',
        'product_variant_id',
        'stock_id',
        'voucher_id',
        'customer_email',
        'customer_phone',
        'amount',
        'discount_amount',
        'fee',
        'total_payment',
        'payment_method',
        'payment_ref',
        'status',
        'paid_at',
        'expired_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'discount_amount' => 'integer',
            'fee' => 'integer',
            'total_payment' => 'integer',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * True jika order ini hasil checkout cart (multi-varian dalam 1 transaksi).
     * Order single-item (instant checkout) cukup punya 1 OrderItem atau 0 (legacy).
     */
    public function isCart(): bool
    {
        return $this->items()->count() > 1;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
