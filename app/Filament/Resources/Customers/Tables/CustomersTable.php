<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use App\Support\Filament\MasterDataStatusActions;
use Filament\Actions\ActionGroup;
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
                TextColumn::make('code')
                    ->label('الرمز')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                TextColumn::make('name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Customer $record): ?string => $record->owner_name),
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
                TextColumn::make('area.name_ar')
                    ->label('المنطقة')
                    ->searchable()
                    ->placeholder('-')
                    ->description(fn (Customer $record): ?string => $record->route?->name),
                TextColumn::make('mobile')
                    ->label('الموبايل')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('payment_type')
                    ->label('الدفع المعتاد')
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
                TextColumn::make('credit_limit')
                    ->label('حد الائتمان')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('area_id')
                    ->label('المنطقة')
                    ->relationship('area', 'name_ar')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('route_id')
                    ->label('خط التوزيع')
                    ->relationship('route', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_type')
                    ->label('طريقة الدفع')
                    ->options([
                        'cash' => 'نقدي',
                        'credit' => 'آجل',
                        'weekly' => 'أسبوعي',
                        'monthly' => 'شهري',
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
                        ->label('تعديل العميل')
                        ->modalHeading('تعديل عميل')
                        ->slideOver()
                        ->visible(fn (Customer $record): bool => auth()->user()?->can('update', $record) === true),
                    MasterDataStatusActions::activate('العميل'),
                    MasterDataStatusActions::deactivate('العميل'),
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
            ->emptyStateIcon('heroicon-o-building-storefront')
            ->emptyStateHeading('لا يوجد عملاء في الدليل')
            ->emptyStateDescription('أضف أول عميل، أو غيّر عوامل التصفية للعثور على عميل غير فعال.');
    }
}
