<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('الرمز')->searchable()->sortable(),
                TextColumn::make('name')->label('العميل')->searchable()->sortable(),

                TextColumn::make('customer_type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'grocery' => 'بقالية',
                        'supermarket' => 'سوبر ماركت',
                        'restaurant' => 'مطعم',
                        'wholesaler' => 'جملة',
                        'mini_market' => 'ميني ماركت',
                        'other' => 'أخرى',
                        default => $state ?? '-',
                    })
                    ->color('primary'),

                TextColumn::make('phone')->label('الهاتف')->searchable()->toggleable(),
                TextColumn::make('area.name_ar')->label('المنطقة')->searchable()->placeholder('-'),
                TextColumn::make('route.name')->label('خط التوزيع')->searchable()->placeholder('-')->toggleable(),

                TextColumn::make('payment_type')
                    ->label('الدفع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash' => 'نقدي',
                        'credit' => 'آجل',
                        'weekly' => 'أسبوعي',
                        'monthly' => 'شهري',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'cash' => 'success',
                        'credit' => 'warning',
                        'weekly' => 'info',
                        'monthly' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'active' ? 'فعال' : 'غير فعال')
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('customer_type')
                    ->label('نوع العميل')
                    ->options([
                        'grocery' => 'بقالية',
                        'supermarket' => 'سوبر ماركت',
                        'restaurant' => 'مطعم',
                        'wholesaler' => 'موزع / جملة',
                        'mini_market' => 'ميني ماركت',
                        'other' => 'أخرى',
                    ]),

                SelectFilter::make('area_id')->label('المنطقة')->relationship('area', 'name_ar')->searchable()->preload(),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعال',
                        'inactive' => 'غير فعال',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->canManageMasterData() === true)
                    ->label('تعديل')
                    ->modalHeading('تعديل عميل')
                    ->slideOver(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('حذف المحدد')
                        ->visible(fn (): bool => auth()->user()?->canManageMasterData() === true),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}