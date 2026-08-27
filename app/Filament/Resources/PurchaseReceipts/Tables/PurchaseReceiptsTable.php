<?php

namespace App\Filament\Resources\PurchaseReceipts\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchaseReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('رقم الاستلام')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('purchaseOrder.purchase_order_number')
                    ->label('أمر الشراء')
                    ->searchable(),
                TextColumn::make('purchaseOrder.supplier.name')
                    ->label('المورد')
                    ->searchable(),
                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->searchable(),
                TextColumn::make('receipt_date')
                    ->label('تاريخ الاستلام')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('الأصناف'),
                TextColumn::make('total_amount')
                    ->label('الإجمالي')
                    ->money('SYP')
                    ->weight('bold'),
                TextColumn::make('creator.name')
                    ->label('بواسطة')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('receipt_date', 'desc');
    }
}
