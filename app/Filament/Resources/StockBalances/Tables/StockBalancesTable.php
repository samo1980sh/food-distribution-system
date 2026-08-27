<?php

namespace App\Filament\Resources\StockBalances\Tables;

use App\Models\StockBalance;
use App\Support\Formatting\QuantityFormatter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockBalancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.type')
                    ->label('نوع المستودع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'main' => 'رئيسي',
                        'branch' => 'فرعي',
                        'vehicle' => 'سيارة',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'main' => 'primary',
                        'branch' => 'info',
                        'vehicle' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product.name_ar')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('batch_number')
                    ->label('رقم التشغيلة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('expiry_date')
                    ->label('تاريخ الصلاحية')
                    ->date('Y-m-d')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('quantity')
                    ->label('الكمية')
                    ->state(fn (StockBalance $record): string => QuantityFormatter::formatWithUnit(
                        (float) $record->quantity,
                        $record->product?->unit,
                    ))
                    ->sortable()
                    ->badge()
                    ->color(fn (StockBalance $record): string => ((float) $record->quantity) > 0 ? 'success' : 'gray'),


                TextColumn::make('replenishment_status')
                    ->label('حالة التزويد')
                    ->state(fn (StockBalance $record): string => self::stockLevelLabel($record))
                    ->badge()
                    ->color(fn (StockBalance $record): string => self::stockLevelColor($record))
                    ->tooltip(fn (StockBalance $record): string => self::stockLevelTooltip($record)),

                TextColumn::make('average_unit_cost')
                    ->label('متوسط تكلفة الوحدة')
                    ->money('SYP')
                    ->sortable(),

                TextColumn::make('inventory_value')
                    ->label('قيمة المخزون')
                    ->getStateUsing(
                        fn (StockBalance $record): float =>
                            (float) $record->quantity
                            * (float) $record->average_unit_cost
                    )
                    ->money('SYP'),

                TextColumn::make('updated_at')
                    ->label('آخر تحديث')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('المستودع')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('product_id')
                    ->label('المنتج')
                    ->relationship('product', 'name_ar')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('updated_at', 'desc');
    }

    private static function stockLevelState(StockBalance $record): string
    {
        if ($record->warehouse?->type === 'vehicle') {
            return 'operational';
        }

        $quantity = (float) ($record->getAttribute('warehouse_product_quantity') ?? $record->quantity);
        $minimum = (float) ($record->product?->min_stock ?? 0);

        if ($quantity <= 0) {
            return 'out_of_stock';
        }

        if ($minimum > 0 && $quantity <= $minimum) {
            return 'low_stock';
        }

        return 'healthy';
    }

    private static function stockLevelLabel(StockBalance $record): string
    {
        return match (self::stockLevelState($record)) {
            'out_of_stock' => 'نافد',
            'low_stock' => 'منخفض',
            'healthy' => 'متاح',
            default => 'تشغيلي',
        };
    }

    private static function stockLevelColor(StockBalance $record): string
    {
        return match (self::stockLevelState($record)) {
            'out_of_stock' => 'danger',
            'low_stock' => 'warning',
            'healthy' => 'success',
            default => 'gray',
        };
    }

    private static function stockLevelTooltip(StockBalance $record): string
    {
        if ($record->warehouse?->type === 'vehicle') {
            return 'مخزون السيارة تشغيلي ويُغذّى عبر حمولة السيارة المعتمدة.';
        }

        $quantity = (float) ($record->getAttribute('warehouse_product_quantity') ?? $record->quantity);
        $minimum = (float) ($record->product?->min_stock ?? 0);

        return 'إجمالي المنتج في المستودع: '.self::formatQuantity($quantity)
            .' | الحد الأدنى: '.self::formatQuantity($minimum);
    }

    private static function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }
}
