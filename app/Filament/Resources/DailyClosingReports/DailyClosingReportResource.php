<?php

namespace App\Filament\Resources\DailyClosingReports;

use App\Filament\Resources\DailyClosingReports\Pages\ManageDailyClosingReports;
use App\Filament\Resources\DailyClosingReports\Tables\DailyClosingReportsTable;
use App\Models\DailyClosing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DailyClosingReportResource extends Resource
{
    protected static ?string $model = DailyClosing::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $recordTitleAttribute = 'closing_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التقارير';
    }

    public static function getNavigationLabel(): string
    {
        return 'تقرير الإغلاق اليومي';
    }

    public static function getModelLabel(): string
    {
        return 'تقرير إغلاق يومي';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تقرير الإغلاق اليومي';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canManageDailyClosings() === true;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageDailyClosings() === true;
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
        return DailyClosingReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDailyClosingReports::route('/'),
        ];
    }
}