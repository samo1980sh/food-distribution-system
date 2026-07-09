<?php

namespace App\Filament\Resources\SalesReturns;

use App\Filament\Resources\SalesReturns\Pages\ManageSalesReturns;
use App\Filament\Resources\SalesReturns\Schemas\SalesReturnForm;
use App\Filament\Resources\SalesReturns\Tables\SalesReturnsTable;
use App\Models\SalesReturn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SalesReturnResource extends Resource
{
    protected static ?string $model = SalesReturn::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $recordTitleAttribute = 'return_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'المبيعات والتحصيل';
    }

    public static function getNavigationLabel(): string
    {
        return 'مرتجعات المبيعات';
    }

    public static function getModelLabel(): string
    {
        return 'مرتجع بيع';
    }

    public static function getPluralModelLabel(): string
    {
        return 'مرتجعات المبيعات';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }


    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canManageSalesAndCollections() === true;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageSalesAndCollections() === true;
    }
    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole([
            \App\Models\User::ROLE_SUPER_ADMIN,
            \App\Models\User::ROLE_MANAGER,
            \App\Models\User::ROLE_SUPERVISOR,
        ]) === true;
    }
    public static function form(Schema $schema): Schema
    {
        return SalesReturnForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesReturnsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSalesReturns::route('/'),
        ];
    }
}
