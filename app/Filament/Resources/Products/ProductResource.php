<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $recordTitleAttribute = 'name_ar';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'المخزون';
    }

    public static function getNavigationLabel(): string
    {
        return 'المنتجات';
    }

    public static function getModelLabel(): string
    {
        return 'منتج';
    }

    public static function getPluralModelLabel(): string
    {
        return 'المنتجات';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }


    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canManageInventory() === true;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageInventory() === true;
    }
    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProducts::route('/'),
        ];
    }
}
