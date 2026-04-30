<?php

namespace App\Filament\Resources\QuickProducts\Support;

use App\Models\ProductVariant;
use App\Models\Stock;

/**
 * Helper untuk fitur "Quick Tools Product".
 * Parse textarea bulk stok (tiap baris: email|password atau email|password|info)
 * lalu bikin Stock terenkripsi untuk varian.
 */
class QuickProductBulkStock
{
    /**
     * Parse text bulk stok jadi array baris [email,password,info].
     * Skip baris kosong dan baris diawali #.
     *
     * @return array<int, array{email: string, password: string, info: ?string}>
     */
    public static function parse(?string $text): array
    {
        if (! $text) {
            return [];
        }

        $rows = [];
        $lines = preg_split('/\r\n|\n|\r/', $text) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $email = $parts[0] ?? '';
            $password = $parts[1] ?? '';
            if ($email === '' || $password === '') {
                continue;
            }
            $rows[] = [
                'email' => $email,
                'password' => $password,
                'info' => isset($parts[2]) && $parts[2] !== '' ? $parts[2] : null,
            ];
        }

        return $rows;
    }

    /**
     * Apply bulk stok ke varian. Kalau $replace=true, hapus dulu stok yang
     * BELUM TERJUAL milik varian ini lalu tambahkan baris baru. Kalau false,
     * append.
     *
     * Return jumlah stok yang dibuat.
     */
    public static function apply(ProductVariant $variant, ?string $bulkText, bool $replace = false): int
    {
        $rows = self::parse($bulkText);
        if (empty($rows) && ! $replace) {
            return 0;
        }

        if ($replace) {
            // Cuma hapus yang belum terjual — yang sudah terjual harus tetap
            // ada untuk audit invoice / kredensial pembeli.
            Stock::where('product_variant_id', $variant->id)
                ->where('is_sold', false)
                ->delete();
        }

        $created = 0;
        foreach ($rows as $row) {
            Stock::create([
                'product_variant_id' => $variant->id,
                'email_or_phone' => $row['email'],
                'password' => $row['password'],
                'additional_info' => $row['info'],
                'is_sold' => false,
            ]);
            $created++;
        }

        return $created;
    }
}
