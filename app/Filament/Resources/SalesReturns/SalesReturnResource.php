<?php

namespace App\Filament\Resources\SalesReturns;

use App\Filament\Resources\SalesReturns\Pages\CreateSalesReturn;
use App\Filament\Resources\SalesReturns\Pages\EditSalesReturn;
use App\Filament\Resources\SalesReturns\Pages\ListSalesReturns;
use App\Filament\Resources\SalesReturns\Pages\ViewSalesReturn;
use App\Filament\Resources\SalesReturns\Schemas\SalesReturnForm;
use App\Filament\Resources\SalesReturns\Schemas\SalesReturnInfolist;
use App\Filament\Resources\SalesReturns\Tables\SalesReturnsTable;
use App\Models\SalesReturn;
use App\Services\Authorization\AccessScopeService;
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
        return 'المراجعة والاعتماد';
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

    public static function canCreate(): bool
    {
        return auth()->user()?->can('createAdminException', SalesReturn::class) === true;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(AccessScopeService::class)->apply(SalesReturn::query())
            ->where('status', 'draft')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return SalesReturnForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesReturnInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesReturnsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesReturns::route('/'),
            'create' => CreateSalesReturn::route('/create'),
            'view' => ViewSalesReturn::route('/{record}'),
            'edit' => EditSalesReturn::route('/{record}/edit'),
        ];
    }
}
