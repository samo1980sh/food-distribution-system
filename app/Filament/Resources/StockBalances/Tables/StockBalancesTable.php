<?php

namespace App\Filament\Resources\StockBalances\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->badge()
                    ->color(fn ($state): string => ((float) $state) > 0 ? 'success' : 'gray'),

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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('حذف المحدد'),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}