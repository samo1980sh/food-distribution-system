<?php

namespace App\Filament\Resources\SalesInvoices;

use App\Filament\Resources\SalesInvoices\Pages\CreateSalesInvoice;
use App\Filament\Resources\SalesInvoices\Pages\EditSalesInvoice;
use App\Filament\Resources\SalesInvoices\Pages\ListSalesInvoices;
use App\Filament\Resources\SalesInvoices\Pages\ViewSalesInvoice;
use App\Filament\Resources\SalesInvoices\Schemas\SalesInvoiceForm;
use App\Filament\Resources\SalesInvoices\Schemas\SalesInvoiceInfolist;
use App\Filament\Resources\SalesInvoices\Tables\SalesInvoicesTable;
use App\Models\SalesInvoice;
use App\Services\Authorization\AccessScopeService;
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
        return 'المراجعة والاعتماد';
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

    public static function canCreate(): bool
    {
        return auth()->user()?->can('createAdminException', SalesInvoice::class) === true;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(AccessScopeService::class)->apply(SalesInvoice::query())
            ->where('status', 'draft')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return SalesInvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesInvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesInvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesInvoices::route('/'),
            'create' => CreateSalesInvoice::route('/create'),
            'view' => ViewSalesInvoice::route('/{record}'),
            'edit' => EditSalesInvoice::route('/{record}/edit'),
        ];
    }
}
