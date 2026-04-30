<?php

namespace App\Filament\Reseller\Resources\Stocks\Pages;

use App\Filament\Reseller\Resources\Stocks\StockResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStock extends CreateRecord
{
    protected static string $resource = StockResource::class;
}
