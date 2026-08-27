<?php

namespace App\Filament\Resources\PurchaseReceipts;

use App\Filament\Resources\PurchaseReceipts\Pages\ManagePurchaseReceipts;
use App\Filament\Resources\PurchaseReceipts\Tables\PurchaseReceiptsTable;
use App\Models\PurchaseReceipt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PurchaseReceiptResource extends Resource
{
    protected static ?string $model = PurchaseReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $recordTitleAttribute = 'receipt_number';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'المشتريات';
    }

    public static function getNavigationLabel(): string
    {
        return 'استلامات المشتريات';
    }

    public static function getModelLabel(): string
    {
        return 'استلام مشتريات';
    }

    public static function getPluralModelLabel(): string
    {
        return 'استلامات المشتريات';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return PurchaseReceiptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePurchaseReceipts::route('/'),
        ];
    }
}
