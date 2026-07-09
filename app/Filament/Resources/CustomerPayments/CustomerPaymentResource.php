<?php

namespace App\Filament\Resources\CustomerPayments;

use App\Filament\Resources\CustomerPayments\Pages\ManageCustomerPayments;
use App\Filament\Resources\CustomerPayments\Schemas\CustomerPaymentForm;
use App\Filament\Resources\CustomerPayments\Tables\CustomerPaymentsTable;
use App\Models\CustomerPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CustomerPaymentResource extends Resource
{
    protected static ?string $model = CustomerPayment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $recordTitleAttribute = 'payment_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'المبيعات والتحصيل';
    }

    public static function getNavigationLabel(): string
    {
        return 'تحصيلات العملاء';
    }

    public static function getModelLabel(): string
    {
        return 'تحصيل عميل';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تحصيلات العملاء';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }


    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canManageSalesAndCollections() === true;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageSalesAndCollections() === true;
    }
    public static function form(Schema $schema): Schema
    {
        return CustomerPaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerPaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCustomerPayments::route('/'),
        ];
    }
}
