<?php

namespace App\Filament\Resources\VehicleStockReports;

use App\Filament\Resources\VehicleStockReports\Pages\ManageVehicleStockReports;
use App\Filament\Resources\VehicleStockReports\Tables\VehicleStockReportsTable;
use App\Models\StockBalance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehicleStockReportResource extends Resource
{
    protected static ?string $model = StockBalance::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التقارير';
    }

    public static function getNavigationLabel(): string
    {
        return 'تقرير مخزون السيارات';
    }

    public static function getModelLabel(): string
    {
        return 'رصيد مخزون سيارة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تقرير مخزون السيارات';
    }

    public static function getNavigationSort(): ?int
    {
        return 70;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canManageInventory() === true;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageInventory() === true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('quantity', '!=', 0)
            ->whereHas(
                'warehouse',
                fn (Builder $query): Builder => $query
                    ->where('type', 'vehicle'),
            );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return VehicleStockReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVehicleStockReports::route('/'),
        ];
    }
}
