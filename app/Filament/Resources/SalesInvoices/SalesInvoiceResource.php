<?php

namespace App\Filament\Resources\SalesInvoices;

use App\Filament\Resources\SalesInvoices\Pages\ManageSalesInvoices;
use App\Filament\Resources\SalesInvoices\Schemas\SalesInvoiceForm;
use App\Filament\Resources\SalesInvoices\Tables\SalesInvoicesTable;
use App\Models\SalesInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = SalesInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'المبيعات والتحصيل';
    }

    public static function getNavigationLabel(): string
    {
        return 'فواتير البيع';
    }

    public static function getModelLabel(): string
    {
        return 'فاتورة بيع';
    }

    public static function getPluralModelLabel(): string
    {
        return 'فواتير البيع';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public static function form(Schema $schema): Schema
    {
        return SalesInvoiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesInvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSalesInvoices::route('/'),
        ];
    }
}
