<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramBotState extends Model
{
    protected $fillable = ['chat_id', 'state', 'payload'];

    protected $casts = [
        'payload' => 'array',
    ];

    public const STATE_IDLE = 'idle';

    public const STATE_BROWSING = 'browsing';

    public const STATE_CART = 'cart';

    public const STATE_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATE_AWAITING_BROADCAST = 'awaiting_broadcast';

    public static function for(string $chatId): self
    {
        return self::firstOrCreate(
            ['chat_id' => $chatId],
            ['state' => self::STATE_IDLE, 'payload' => []]
        );
    }

    public function setState(string $state, array $payload = []): self
    {
        $this->state = $state;
        $this->payload = $payload;
        $this->save();

        return $this;
    }

    public function reset(): self
    {
        return $this->setState(self::STATE_IDLE, []);
    }

    public function getCart(): array
    {
        return $this->payload['cart'] ?? [];
    }

    public function setCart(array $cart): self
    {
        $payload = $this->payload ?? [];
        $payload['cart'] = $cart;
        $this->payload = $payload;
        $this->save();

        return $this;
    }

    public function getPendingQty(int $variantId): int
    {
        return (int) (($this->payload['pending_qty'][$variantId] ?? 1));
    }

    public function setPendingQty(int $variantId, int $qty): self
    {
        $payload = $this->payload ?? [];
        $payload['pending_qty'][$variantId] = max(1, $qty);
        $this->payload = $payload;
        $this->save();

        return $this;
    }

    public function clearPendingQty(int $variantId): self
    {
        $payload = $this->payload ?? [];
        if (isset($payload['pending_qty'][$variantId])) {
            unset($payload['pending_qty'][$variantId]);
        }
        $this->payload = $payload;
        $this->save();

        return $this;
    }
}
