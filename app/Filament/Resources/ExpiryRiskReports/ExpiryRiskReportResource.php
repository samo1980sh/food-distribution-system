<?php

namespace App\Filament\Resources\ExpiryRiskReports;

use App\Filament\Resources\ExpiryRiskReports\Pages\ManageExpiryRiskReports;
use App\Filament\Resources\ExpiryRiskReports\Tables\ExpiryRiskReportsTable;
use App\Models\StockBalance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpiryRiskReportResource extends Resource
{
    protected static ?string $model = StockBalance::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التقارير';
    }

    public static function getNavigationLabel(): string
    {
        return 'تقرير المواد القريبة من الانتهاء';
    }

    public static function getModelLabel(): string
    {
        return 'رصيد صلاحية';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تقرير المواد القريبة من الانتهاء';
    }

    public static function getNavigationSort(): ?int
    {
        return 73;
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
            ->where('quantity', '>', 0)
            ->whereHas(
                'product',
                fn (Builder $query): Builder => $query
                    ->where('has_expiry', true),
            );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return ExpiryRiskReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpiryRiskReports::route('/'),
        ];
    }
}
