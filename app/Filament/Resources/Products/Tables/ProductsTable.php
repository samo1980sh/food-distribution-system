<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use App\Support\Filament\MasterDataStatusActions;
use Filament\Actions\ActionGroup;
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
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                TextColumn::make('name_ar')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Product $record): ?string => $record->barcode),
                TextColumn::make('category.name_ar')
                    ->label('التصنيف')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('unit.name_ar')
                    ->label('الوحدة')
                    ->placeholder('-'),
                TextColumn::make('sale_price')
                    ->label('سعر البيع')
                    ->money('SYP')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('wholesale_price')
                    ->label('سعر الجملة')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchase_price')
                    ->label('سعر الشراء المرجعي')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('min_stock')
                    ->label('حد المخزون')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('has_expiry')
                    ->label('الصلاحية')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'مطلوبة' : 'غير مطلوبة')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'active' ? 'فعال' : 'غير فعال')
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('updated_at')
                    ->label('آخر تحديث')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('التصنيف')
                    ->relationship('category', 'name_ar')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('unit_id')
                    ->label('الوحدة')
                    ->relationship('unit', 'name_ar')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('has_expiry')
                    ->label('تتبع الصلاحية')
                    ->options([
                        '1' => 'مطلوبة',
                        '0' => 'غير مطلوبة',
                    ]),
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعال',
                        'inactive' => 'غير فعال',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('تعديل المنتج')
                        ->modalHeading('تعديل منتج')
                        ->slideOver()
                        ->visible(fn (Product $record): bool => auth()->user()?->can('update', $record) === true),
                    MasterDataStatusActions::activate('المنتج'),
                    MasterDataStatusActions::deactivate('المنتج'),
                ])
                    ->label('الإجراءات')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading('لا توجد منتجات في الدليل')
            ->emptyStateDescription('أضف أول منتج، أو غيّر عوامل التصفية للعثور على منتج غير فعال.');
    }
}
