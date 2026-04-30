<?php

namespace App\Filament\Resources\QuickProducts\Tables;

use App\Models\Stock;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuickProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['variants', 'category'])->withCount('variants'))
            ->columns([
                ImageColumn::make('image')
                    ->label('Foto')
                    ->disk('public')
                    ->square()
                    ->size(40),

                TextColumn::make('id')
                    ->label('Kode')
                    ->prefix('PRD-')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->limit(40)
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('sold_count')
                    ->label('Terjual')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('variants_count')
                    ->label('Variasi')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('total_stock')
                    ->label('Stok')
                    ->state(function ($record): int {
                        $variantIds = $record->variants->pluck('id');
                        if ($variantIds->isEmpty()) {
                            return 0;
                        }

                        return Stock::whereIn('product_variant_id', $variantIds)
                            ->where('is_sold', false)
                            ->count();
                    })
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),

                TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
