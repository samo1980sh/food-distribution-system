<?php

namespace App\Filament\Resources\ProductCategories\Tables;

use App\Enums\PermissionName;
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
                TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
                TextColumn::make('name_ar')->label('الاسم')->searchable()->sortable(),
                TextColumn::make('parent.name_ar')->label('التصنيف الأب')->searchable()->placeholder('-')->toggleable(),
                TextColumn::make('sort_order')->label('الترتيب')->sortable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'active' ? 'فعال' : 'غير فعال')
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعال',
                        'inactive' => 'غير فعال',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->can(PermissionName::PRODUCT_CATEGORIES_UPDATE->value) === true)
                    ->label('تعديل')
                    ->modalHeading('تعديل تصنيف')
                    ->slideOver(),
            ])
            ->toolbarActions([])
            ->defaultSort('sort_order');
    }
}