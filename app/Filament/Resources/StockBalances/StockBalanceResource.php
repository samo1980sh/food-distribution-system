<?php

namespace App\Filament\Resources\StockBalances;

use App\Filament\Clusters\InventoryCluster;
use App\Filament\Resources\StockBalances\Pages\ManageStockBalances;
use App\Filament\Resources\StockBalances\Tables\StockBalancesTable;
use App\Models\StockBalance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StockBalanceResource extends Resource
{
    protected static ?string $model = StockBalance::class;

    protected static ?string $cluster = InventoryCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationLabel(): string
    {
        return 'المخزون الحالي';
    }

    public static function getModelLabel(): string
    {
        return 'رصيد مخزون';
    }

    public static function getPluralModelLabel(): string
    {
        return 'أرصدة المخزون';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }


    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->addSelect([
                'warehouse_product_quantity' => DB::table('stock_balances as warehouse_product_totals')
                    ->selectRaw('COALESCE(SUM(warehouse_product_totals.quantity), 0)')
                    ->whereColumn('warehouse_product_totals.warehouse_id', 'stock_balances.warehouse_id')
                    ->whereColumn('warehouse_product_totals.product_id', 'stock_balances.product_id'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return StockBalancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStockBalances::route('/'),
        ];
    }
}