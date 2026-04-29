<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Models\Review;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->label('Produk')->limit(30)->searchable(),
                TextColumn::make('reviewer_name')->label('Reviewer')->searchable(),
                TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state))
                    ->color('warning'),
                TextColumn::make('comment')->label('Komentar')->limit(60)->wrap(),
                IconColumn::make('is_approved')->label('Approved')->boolean(),
                TextColumn::make('created_at')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_approved')
                    ->label('Status')
                    ->trueLabel('Disetujui')
                    ->falseLabel('Menunggu Moderasi'),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Review $r) => ! $r->is_approved)
                    ->action(fn (Review $r) => $r->update(['is_approved' => true])),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Review $r) => $r->is_approved)
                    ->action(fn (Review $r) => $r->update(['is_approved' => false])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_bulk')
                        ->label('Setujui Terpilih')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['is_approved' => true])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
