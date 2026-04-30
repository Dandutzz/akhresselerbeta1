<?php

namespace App\Filament\Resources\QuickProducts\Pages;

use App\Filament\Resources\QuickProducts\QuickProductResource;
use App\Filament\Resources\QuickProducts\Support\QuickProductBulkStock;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditQuickProduct extends EditRecord
{
    protected static string $resource = QuickProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Hidrasi form: load variants dengan key 'id' supaya update bisa match.
     * Field _bulk_stock dan _replace_stock dibiarkan kosong (admin isi hanya
     * kalau mau menambah stok).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Product $product */
        $product = $this->record;

        $variants = $product->variants()->orderBy('id')->get()->map(function (ProductVariant $v) {
            return [
                'id' => $v->id,
                'name' => $v->name,
                'price' => $v->price,
                'is_auto_send' => $v->is_auto_send === null ? '' : ($v->is_auto_send ? '1' : '0'),
                'warranty_days' => $v->warranty_days,
                'share_type' => $v->share_type,
                '_bulk_stock' => '',
                '_replace_stock' => false,
            ];
        })->all();

        $data['variants'] = $variants;

        return $data;
    }

    /**
     * Save: update produk + sync varian (hapus yang tidak ada lagi, update yang
     * ada, create yang baru) + parse bulk stok per varian.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $variantsData = $data['variants'] ?? [];
        unset($data['variants']);

        // products.price NOT NULL — sync ke varian termurah. Konsisten dengan
        // CreateQuickProduct: kalau semua harga = 0, simpan 0 (bukan biarkan
        // value lama).
        $prices = array_filter(array_map(fn ($v) => (int) ($v['price'] ?? 0), $variantsData));
        $data['price'] = $prices ? min($prices) : 0;

        return DB::transaction(function () use ($record, $data, $variantsData) {
            /** @var Product $record */
            $record->update($data);

            $existingIds = $record->variants()->pluck('id')->all();
            $submittedIds = [];
            $stocksCreated = 0;

            foreach ($variantsData as $row) {
                $bulk = $row['_bulk_stock'] ?? null;
                $replace = (bool) ($row['_replace_stock'] ?? false);
                $variantId = $row['id'] ?? null;
                unset($row['_bulk_stock'], $row['_replace_stock'], $row['id']);

                if ($variantId) {
                    /** @var ProductVariant|null $variant */
                    $variant = $record->variants()->where('id', $variantId)->first();
                    if (! $variant) {
                        // ID submit tapi varian sudah dihapus di tab lain — buat baru.
                        $variant = $record->variants()->create($row);
                    } else {
                        $variant->update($row);
                    }
                } else {
                    $variant = $record->variants()->create($row);
                }
                $submittedIds[] = $variant->id;

                $stocksCreated += QuickProductBulkStock::apply($variant, $bulk, $replace);
            }

            // Hapus varian yang ada di DB tapi tidak di-submit.
            $toDelete = array_diff($existingIds, $submittedIds);
            if (! empty($toDelete)) {
                // Cascade: ProductVariant migration sudah on delete cascade ke stocks.
                $record->variants()->whereIn('id', $toDelete)->delete();
            }

            if ($stocksCreated > 0) {
                Notification::make()
                    ->success()
                    ->title("Stok ditambahkan: {$stocksCreated} akun")
                    ->send();
            }

            return $record;
        });
    }
}
