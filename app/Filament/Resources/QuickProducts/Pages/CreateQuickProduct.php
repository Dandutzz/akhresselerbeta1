<?php

namespace App\Filament\Resources\QuickProducts\Pages;

use App\Filament\Resources\QuickProducts\QuickProductResource;
use App\Filament\Resources\QuickProducts\Support\QuickProductBulkStock;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateQuickProduct extends CreateRecord
{
    protected static string $resource = QuickProductResource::class;

    /**
     * Override default save: simpan Product + sinkron varian + parse bulk stok
     * dalam 1 transaction.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $variantsData = $data['variants'] ?? [];
        unset($data['variants']);

        // products.price NOT NULL — set ke harga varian termurah (display "mulai dari").
        $prices = array_filter(array_map(fn ($v) => (int) ($v['price'] ?? 0), $variantsData));
        $data['price'] = $prices ? min($prices) : 0;

        return DB::transaction(function () use ($data, $variantsData) {
            /** @var Product $product */
            $product = Product::create($data);

            $stocksCreated = 0;
            foreach ($variantsData as $row) {
                $bulk = $row['_bulk_stock'] ?? null;
                $replace = (bool) ($row['_replace_stock'] ?? false);
                unset($row['_bulk_stock'], $row['_replace_stock'], $row['id']);

                /** @var ProductVariant $variant */
                $variant = $product->variants()->create($row);

                $stocksCreated += QuickProductBulkStock::apply($variant, $bulk, $replace);
            }

            if ($stocksCreated > 0) {
                Notification::make()
                    ->success()
                    ->title("Stok ditambahkan: {$stocksCreated} akun")
                    ->send();
            }

            return $product;
        });
    }
}
