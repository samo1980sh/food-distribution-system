<?php

namespace App\Filament\Resources\CustomerPaymentReports;

use App\Filament\Resources\CustomerPaymentReports\Pages\ManageCustomerPaymentReports;
use App\Filament\Resources\CustomerPaymentReports\Tables\CustomerPaymentReportsTable;
use App\Models\CustomerPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CustomerPaymentReportResource extends Resource
{
    protected static ?string $model = CustomerPayment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $recordTitleAttribute = 'payment_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التقارير';
    }

    public static function getNavigationLabel(): string
    {
        return 'تقرير التحصيلات';
    }

    public static function getModelLabel(): string
    {
        return 'تقرير تحصيل';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تقرير التحصيلات';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
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

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return CustomerPaymentReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCustomerPaymentReports::route('/'),
        ];
    }
}