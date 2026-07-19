<?php

namespace App\Filament\Resources\VehicleLoadReports;

use App\Enums\PermissionName;
use App\Filament\Resources\VehicleLoadReports\Pages\ManageVehicleLoadReports;
use App\Filament\Resources\VehicleLoadReports\Tables\VehicleLoadReportsTable;
use App\Models\VehicleLoad;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class VehicleLoadReportResource extends Resource
{
    protected static ?string $model = VehicleLoad::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $recordTitleAttribute = 'load_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'تقارير المركبات والتوزيع';
    }

    public static function getNavigationLabel(): string
    {
        return 'تقرير تحميلات السيارات';
    }

    public static function getModelLabel(): string
    {
        return 'تقرير تحميل سيارة';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تقرير تحميلات السيارات';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(PermissionName::REPORT_VEHICLE_LOADS->value) === true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return VehicleLoadReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVehicleLoadReports::route('/'),
        ];
    }
}
