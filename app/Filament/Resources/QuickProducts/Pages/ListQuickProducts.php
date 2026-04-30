<?php

namespace App\Filament\Resources\QuickProducts\Pages;

use App\Filament\Resources\QuickProducts\QuickProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuickProducts extends ListRecords
{
    protected static string $resource = QuickProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('+ Tambah Produk'),
        ];
    }
}
