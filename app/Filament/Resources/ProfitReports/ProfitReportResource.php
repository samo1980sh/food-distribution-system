<?php

namespace App\Filament\Resources\ProfitReports;

use App\Filament\Resources\ProfitReports\Pages\ManageProfitReports;
use App\Filament\Resources\ProfitReports\Tables\ProfitReportsTable;
use App\Models\ProfitReportEntry;
use App\Services\Reports\ProfitReportQuery;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProfitReportResource extends Resource
{
    protected static ?string $model = ProfitReportEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $recordTitleAttribute = 'document_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التقارير';
    }

    public static function getNavigationLabel(): string
    {
        return 'تقرير الأرباح التقريبية';
    }

    public static function getModelLabel(): string
    {
        return 'حركة ربحية';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تقرير الأرباح التقريبية';
    }

    public static function getNavigationSort(): ?int
    {
        return 71;
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
        return app(ProfitReportQuery::class)->build();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return ProfitReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProfitReports::route('/'),
        ];
    }
}
