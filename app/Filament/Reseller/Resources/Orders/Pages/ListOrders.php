<?php

namespace App\Filament\Reseller\Resources\Orders\Pages;

use App\Filament\Reseller\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
