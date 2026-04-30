<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service untuk operasi saldo (wallet) user. Semua perubahan saldo HARUS
 * lewat service ini supaya:
 *   1. Locking baris user pakai lockForUpdate (race-safe)
 *   2. Selalu ada entry WalletTransaction (audit trail)
 *   3. balance_before / balance_after konsisten
 */
class WalletService
{
    /**
     * Tambah saldo (deposit, refund, admin top-up). Amount harus positif.
     * Return WalletTransaction yang dibuat.
     */
    public static function credit(
        User $user,
        int $amount,
        string $type = WalletTransaction::TYPE_DEPOSIT,
        ?string $note = null,
        ?int $adminId = null,
        ?int $orderId = null,
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount untuk credit harus > 0');
        }

        return DB::transaction(function () use ($user, $amount, $type, $note, $adminId, $orderId) {
            $fresh = User::lockForUpdate()->find($user->id);
            $before = (int) $fresh->balance;
            $after = $before + $amount;
            $fresh->balance = $after;
            $fresh->save();

            return WalletTransaction::create([
                'user_id' => $fresh->id,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'note' => $note,
                'admin_id' => $adminId,
                'order_id' => $orderId,
            ]);
        });
    }

    /**
     * Kurangi saldo (spend, admin deduct). Amount harus positif (akan disimpan
     * sebagai negatif di transaction.amount). Throw kalau saldo kurang.
     */
    public static function debit(
        User $user,
        int $amount,
        string $type = WalletTransaction::TYPE_SPEND,
        ?string $note = null,
        ?int $adminId = null,
        ?int $orderId = null,
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount untuk debit harus > 0');
        }

        return DB::transaction(function () use ($user, $amount, $type, $note, $adminId, $orderId) {
            $fresh = User::lockForUpdate()->find($user->id);
            $before = (int) $fresh->balance;
            if ($before < $amount) {
                throw new InvalidArgumentException("Saldo tidak cukup: {$before} < {$amount}");
            }
            $after = $before - $amount;
            $fresh->balance = $after;
            $fresh->save();

            return WalletTransaction::create([
                'user_id' => $fresh->id,
                'type' => $type,
                'amount' => -$amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'note' => $note,
                'admin_id' => $adminId,
                'order_id' => $orderId,
            ]);
        });
    }

    /**
     * Refund saldo dari order yang di-cancel/refund. Convenience wrapper.
     */
    public static function refundFromOrder(Order $order, ?string $note = null): WalletTransaction
    {
        // Refund SETARA dengan yang user benar-benar bayar (total_payment),
        // bukan amount (subtotal pre-discount). Kalau pakai voucher, amount
        // > total_payment, jadi kalau pakai amount user dapat saldo lebih.
        // Fallback ke amount untuk order legacy yang total_payment-nya null.
        $refundAmount = (int) ($order->total_payment ?? $order->amount);

        return self::credit(
            user: $order->user,
            amount: $refundAmount,
            type: WalletTransaction::TYPE_REFUND,
            note: $note ?? "Refund order {$order->order_code}",
            orderId: $order->id,
        );
    }
}
