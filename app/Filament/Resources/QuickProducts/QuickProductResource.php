<?php

namespace App\Filament\Resources\QuickProducts;

use App\Filament\Resources\QuickProducts\Pages\CreateQuickProduct;
use App\Filament\Resources\QuickProducts\Pages\EditQuickProduct;
use App\Filament\Resources\QuickProducts\Pages\ListQuickProducts;
use App\Filament\Resources\QuickProducts\Schemas\QuickProductForm;
use App\Filament\Resources\QuickProducts\Tables\QuickProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * "Quick Tools Product" — UI ringkas 1-halaman untuk admin yang mau cepat
 * tambah produk + varian + isi stok akun. Pakai model Product yang sama
 * dengan ProductResource biasa, jadi otomatis sinkron — perubahan di sini
 * langsung kelihatan di /admin/products dan sebaliknya.
 */
class QuickProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Quick Tools Product';

    protected static ?string $modelLabel = 'Quick Tools Product';

    protected static ?string $pluralModelLabel = 'Quick Tools Product';

    protected static string|UnitEnum|null $navigationGroup = 'Quick Tools';

    protected static ?int $navigationSort = -10;

    protected static ?string $slug = 'quick-tools-product';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return QuickProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuickProductsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuickProducts::route('/'),
            'create' => CreateQuickProduct::route('/create'),
            'edit' => EditQuickProduct::route('/{record}/edit'),
        ];
    }
}
