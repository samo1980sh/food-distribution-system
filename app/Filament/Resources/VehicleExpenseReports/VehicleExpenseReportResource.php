<?php

namespace App\Filament\Resources\VehicleExpenseReports;

use App\Enums\PermissionName;
use App\Filament\Resources\VehicleExpenseReports\Pages\ManageVehicleExpenseReports;
use App\Filament\Resources\VehicleExpenseReports\Tables\VehicleExpenseReportsTable;
use App\Models\VehicleExpense;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehicleExpenseReportResource extends Resource
{
    protected static ?string $model = VehicleExpense::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $recordTitleAttribute = 'expense_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'تقارير المركبات والتوزيع';
    }

    public static function getNavigationLabel(): string
    {
        return 'تقرير مصاريف السيارات';
    }

    public static function getModelLabel(): string
    {
        return 'مصروف سيارة معتمد';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تقرير مصاريف السيارات';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(PermissionName::REPORT_VEHICLE_EXPENSES->value) === true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'approved');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return VehicleExpenseReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVehicleExpenseReports::route('/'),
        ];
    }
}
