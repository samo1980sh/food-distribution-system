<?php

namespace App\Filament\Resources\SalesReturnReports;

use App\Enums\PermissionName;
use App\Filament\Resources\SalesReturnReports\Pages\ManageSalesReturnReports;
use App\Filament\Resources\SalesReturnReports\Tables\SalesReturnReportsTable;
use App\Models\SalesReturn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SalesReturnReportResource extends Resource
{
    protected static ?string $model = SalesReturn::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $recordTitleAttribute = 'return_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'تقارير المبيعات والعملاء';
    }

    public static function getNavigationLabel(): string
    {
        return 'تقرير مرتجعات البيع';
    }

    public static function getModelLabel(): string
    {
        return 'تقرير مرتجع بيع';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تقرير مرتجعات البيع';
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
        return auth()->user()?->can(PermissionName::REPORT_SALES_RETURNS->value) === true;
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
        return SalesReturnReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSalesReturnReports::route('/'),
        ];
    }
}