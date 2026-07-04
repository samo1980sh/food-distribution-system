<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                TextColumn::make('barcode')->label('الباركود')->searchable()->toggleable(),
                TextColumn::make('name_ar')->label('المنتج')->searchable()->sortable(),
                TextColumn::make('category.name_ar')->label('التصنيف')->searchable()->placeholder('-'),
                TextColumn::make('unit.name_ar')->label('الوحدة')->placeholder('-'),
                TextColumn::make('sale_price')->label('سعر البيع')->money('SYP')->sortable(),
                TextColumn::make('wholesale_price')->label('سعر الجملة')->money('SYP')->sortable()->toggleable(),
                TextColumn::make('min_stock')->label('حد المخزون')->sortable()->toggleable(),

                TextColumn::make('has_expiry')
                    ->label('صلاحية')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'نعم' : 'لا')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'active' ? 'فعال' : 'غير فعال')
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('category_id')->label('التصنيف')->relationship('category', 'name_ar')->searchable()->preload(),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعال',
                        'inactive' => 'غير فعال',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('تعديل')
                    ->modalHeading('تعديل منتج')
                    ->slideOver(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('حذف المحدد'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}