<?php

namespace App\Filament\Resources\ProductCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
                    ->label('تعديل')
                    ->modalHeading('تعديل تصنيف')
                    ->slideOver(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('حذف المحدد'),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}