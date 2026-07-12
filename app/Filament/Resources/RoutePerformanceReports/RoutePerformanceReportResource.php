<?php

namespace App\Filament\Resources\RoutePerformanceReports;

use App\Filament\Resources\RoutePerformanceReports\Pages\ManageRoutePerformanceReports;
use App\Filament\Resources\RoutePerformanceReports\Tables\RoutePerformanceReportsTable;
use App\Models\DistributionRoute;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoutePerformanceReportResource extends Resource
{
    protected static ?string $model = DistributionRoute::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-chart-bar-square';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التقارير';
    }

    public static function getNavigationLabel(): string
    {
        return 'تقرير أداء خطوط التوزيع';
    }

    public static function getModelLabel(): string
    {
        return 'خط توزيع';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تقرير أداء خطوط التوزيع';
    }

    public static function getNavigationSort(): ?int
    {
        return 76;
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
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'area',
            'vehicle',
            'driver',
            'salesRepresentative',
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return RoutePerformanceReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoutePerformanceReports::route('/'),
        ];
    }
}
