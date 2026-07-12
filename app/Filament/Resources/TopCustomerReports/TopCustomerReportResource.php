<?php

namespace App\Filament\Resources\TopCustomerReports;

use App\Filament\Resources\TopCustomerReports\Pages\ManageTopCustomerReports;
use App\Filament\Resources\TopCustomerReports\Tables\TopCustomerReportsTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TopCustomerReportResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التقارير';
    }

    public static function getNavigationLabel(): string
    {
        return 'تقرير العملاء الأكثر شراءً';
    }

    public static function getModelLabel(): string
    {
        return 'عميل';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تقرير العملاء الأكثر شراءً';
    }

    public static function getNavigationSort(): ?int
    {
        return 75;
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
        return parent::getEloquentQuery()
            ->with(['area', 'route'])
            ->whereHas(
                'salesInvoices',
                fn (Builder $query): Builder =>
                    $query->where('status', 'confirmed'),
            );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return TopCustomerReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTopCustomerReports::route('/'),
        ];
    }
}
