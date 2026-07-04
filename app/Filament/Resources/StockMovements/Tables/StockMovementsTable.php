<?php

namespace App\Filament\Resources\StockMovements\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('movement_number')
                    ->label('رقم الحركة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('movement_type')
                    ->label('نوع الحركة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'opening_balance' => 'رصيد افتتاحي',
                        'manual_out' => 'إخراج يدوي',
                        'warehouse_transfer' => 'تحويل',
                        'vehicle_load_transfer' => 'تحميل سيارة',
                        'sales_invoice' => 'فاتورة بيع',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'opening_balance' => 'success',
                        'manual_out' => 'danger',
                        'warehouse_transfer' => 'info',
                        'vehicle_load_transfer' => 'primary',
                        'sales_invoice' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('product.name_ar')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('fromWarehouse.name')
                    ->label('من')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('toWarehouse.name')
                    ->label('إلى')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('batch_number')
                    ->label('التشغيلة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('expiry_date')
                    ->label('الصلاحية')
                    ->date('Y-m-d')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('quantity')
                    ->label('الكمية')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),

                TextColumn::make('unit_cost')
                    ->label('تكلفة الوحدة')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_cost')
                    ->label('الإجمالي')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('creator.name')
                    ->label('بواسطة')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('تاريخ الحركة')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('movement_type')
                    ->label('نوع الحركة')
                    ->options([
                        'opening_balance' => 'رصيد افتتاحي / إدخال',
                        'manual_out' => 'إخراج يدوي',
                        'warehouse_transfer' => 'تحويل بين المستودعات',
                        'vehicle_load_transfer' => 'تحميل سيارة',
                        'sales_invoice' => 'فاتورة بيع',
                    ]),

                SelectFilter::make('product_id')
                    ->label('المنتج')
                    ->relationship('product', 'name_ar')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('from_warehouse_id')
                    ->label('من المستودع')
                    ->relationship('fromWarehouse', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('to_warehouse_id')
                    ->label('إلى المستودع')
                    ->relationship('toWarehouse', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}