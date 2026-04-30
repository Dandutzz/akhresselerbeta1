<?php

namespace App\Filament\Reseller\Resources\Products\Pages;

use App\Filament\Reseller\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
}
