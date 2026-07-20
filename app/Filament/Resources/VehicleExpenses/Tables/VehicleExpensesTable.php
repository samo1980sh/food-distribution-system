<?php

namespace App\Filament\Resources\VehicleExpenses\Tables;

use App\Enums\OperationSource;
use App\Filament\Resources\VehicleExpenses\Actions\VehicleExpenseActions;
use App\Filament\Resources\VehicleExpenses\VehicleExpenseResource;
use App\Models\VehicleExpense;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehicleExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (VehicleExpense $record): string => VehicleExpenseResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('expense_number')
                    ->label('رقم المصروف')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('operation_source')
                    ->label('مصدر العملية')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => OperationSource::labelFor($state))
                    ->color(fn (mixed $state): string => OperationSource::colorFor($state))
                    ->description(fn (VehicleExpense $record): ?string => $record->createdBy?->name)
                    ->sortable(),

                TextColumn::make('expense_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->sortable()
                    ->description(fn (VehicleExpense $record): ?string => $record->route?->name),

                TextColumn::make('expense_type')
                    ->label('نوع المصروف')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'fuel' => 'وقود',
                        'maintenance' => 'صيانة',
                        'washing' => 'غسيل',
                        'fees' => 'رسوم',
                        'parking' => 'موقف',
                        'emergency' => 'طارئ',
                        'other' => 'أخرى',
                        default => $state ?? '-',
                    }),

                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('SYP')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash' => 'نقدي',
                        'bank_transfer' => 'تحويل بنكي',
                        'cheque' => 'شيك',
                        'other' => 'أخرى',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'cash' => 'success',
                        'bank_transfer' => 'info',
                        'cheque' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => 'بانتظار المراجعة',
                        'approved' => 'معتمد',
                        'rejected' => 'مرفوض',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('warehouse.name')
                    ->label('مستودع السيارة')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('driver.name')
                    ->label('السائق')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('salesRepresentative.name')
                    ->label('مندوب المبيعات')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approved_at')
                    ->label('تاريخ الاعتماد')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rejected_at')
                    ->label('تاريخ الرفض')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('operation_source')
                    ->label('مصدر العملية')
                    ->options(OperationSource::options()),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'بانتظار المراجعة',
                        'approved' => 'معتمد',
                        'rejected' => 'مرفوض',
                    ]),

                SelectFilter::make('expense_type')
                    ->label('نوع المصروف')
                    ->options([
                        'fuel' => 'وقود',
                        'maintenance' => 'صيانة',
                        'washing' => 'غسيل',
                        'fees' => 'رسوم',
                        'parking' => 'موقف',
                        'emergency' => 'طارئ',
                        'other' => 'أخرى',
                    ]),

                SelectFilter::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('warehouse_id')
                    ->label('المستودع')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('عرض التفاصيل الكاملة'),
                    EditAction::make()
                        ->label('تعديل المصروف')
                        ->modalHeading('تعديل مصروف سيارة')
                        ->slideOver()
                        ->visible(fn (VehicleExpense $record): bool => auth()->user()?->can('update', $record) === true),
                    VehicleExpenseActions::approve(),
                    VehicleExpenseActions::reject(),
                    VehicleExpenseActions::print(),
                    DeleteAction::make()
                        ->label('حذف المصروف')
                        ->visible(fn (VehicleExpense $record): bool => auth()->user()?->can('delete', $record) === true),
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
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading('لا توجد مصاريف سيارات بعد')
            ->emptyStateDescription('لم تصل مصاريف للمراجعة بعد. استخدم الإدخال الإداري فقط للحالات الاستثنائية.');
    }
}
