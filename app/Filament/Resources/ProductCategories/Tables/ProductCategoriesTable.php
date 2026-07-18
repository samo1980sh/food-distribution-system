<?php

namespace App\Filament\Resources\ProductCategories\Tables;

use App\Models\ProductCategory;
use App\Support\Filament\MasterDataStatusActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('الرمز')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                TextColumn::make('name_ar')
                    ->label('التصنيف')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.name_ar')
                    ->label('التصنيف الأب')
                    ->searchable()
                    ->placeholder('تصنيف رئيسي'),
                TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable(),
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
                SelectFilter::make('parent_id')
                    ->label('التصنيف الأب')
                    ->relationship('parent', 'name_ar')
                    ->searchable()
                    ->preload(),
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
                        ->label('تعديل التصنيف')
                        ->modalHeading('تعديل تصنيف')
                        ->slideOver()
                        ->visible(fn (ProductCategory $record): bool => auth()->user()?->can('update', $record) === true),
                    MasterDataStatusActions::activate('التصنيف'),
                    MasterDataStatusActions::deactivate('التصنيف'),
                ])
                    ->label('الإجراءات')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button(),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order')
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon('heroicon-o-squares-2x2')
            ->emptyStateHeading('لا توجد تصنيفات منتجات')
            ->emptyStateDescription('أضف أول تصنيف، أو غيّر عوامل التصفية لعرض التصنيفات غير الفعالة.');
    }
}
