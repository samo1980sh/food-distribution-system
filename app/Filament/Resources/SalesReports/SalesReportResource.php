<?php

namespace App\Filament\Resources\SalesReports;

use App\Enums\PermissionName;
use App\Filament\Resources\SalesReports\Pages\ManageSalesReports;
use App\Filament\Resources\SalesReports\Tables\SalesReportsTable;
use App\Models\SalesInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SalesReportResource extends Resource
{
    protected static ?string $model = SalesInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التقارير';
    }

    public static function getNavigationLabel(): string
    {
        return 'تقرير المبيعات';
    }

    public static function getModelLabel(): string
    {
        return 'تقرير مبيعات';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تقرير المبيعات';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(PermissionName::REPORT_SALES->value) === true;
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
        return SalesReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSalesReports::route('/'),
        ];
    }
}