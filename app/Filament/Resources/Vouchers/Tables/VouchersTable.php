<?php

namespace App\Filament\Resources\Vouchers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VouchersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),
                TextColumn::make('name')
                    ->label('Nama')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'percent' ? '%' : 'Rp'),
                TextColumn::make('value')
                    ->label('Nilai')
                    ->formatStateUsing(function ($state, $record) {
                        return $record->type === 'percent'
                            ? $state.'%'
                            : 'Rp '.number_format((int) $state, 0, ',', '.');
                    }),
                TextColumn::make('used_count')
                    ->label('Dipakai')
                    ->formatStateUsing(fn ($state, $record) => $record->usage_limit > 0
                        ? $state.' / '.$record->usage_limit
                        : (string) $state),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('expires_at')
                    ->label('Kadaluarsa')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—'),
                TextColumn::make('updated_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
