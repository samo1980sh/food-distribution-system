<?php

namespace App\Filament\Resources\OverdueCustomerReports;

use App\Enums\PermissionName;
use App\Filament\Resources\OverdueCustomerReports\Pages\ManageOverdueCustomerReports;
use App\Filament\Resources\OverdueCustomerReports\Tables\OverdueCustomerReportsTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OverdueCustomerReportResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-circle';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التقارير';
    }

    public static function getNavigationLabel(): string
    {
        return 'تقرير العملاء المتأخرين';
    }

    public static function getModelLabel(): string
    {
        return 'عميل متأخر';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تقرير العملاء المتأخرين';
    }

    public static function getNavigationSort(): ?int
    {
        return 74;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(PermissionName::REPORT_OVERDUE_CUSTOMERS->value) === true;
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
                fn (Builder $query): Builder => $query
                    ->where('status', 'confirmed'),
            );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return OverdueCustomerReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOverdueCustomerReports::route('/'),
        ];
    }
}
