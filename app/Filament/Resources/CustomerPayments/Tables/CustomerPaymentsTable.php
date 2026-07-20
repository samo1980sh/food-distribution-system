<?php

namespace App\Filament\Resources\CustomerPayments\Tables;

use App\Enums\OperationSource;
use App\Filament\Resources\CustomerPayments\Actions\CustomerPaymentActions;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use App\Models\CustomerPayment;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CustomerPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (CustomerPayment $record): string => CustomerPaymentResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('payment_number')
                    ->label('رقم التحصيل')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('operation_source')
                    ->label('مصدر العملية')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => OperationSource::labelFor($state))
                    ->color(fn (mixed $state): string => OperationSource::colorFor($state))
                    ->description(fn (CustomerPayment $record): ?string => $record->creator?->name)
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->description(fn (CustomerPayment $record): ?string => $record->route?->name),

                TextColumn::make('payment_date')
                    ->label('تاريخ التحصيل')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('salesInvoice.invoice_number')
                    ->label('الفاتورة')
                    ->searchable()
                    ->placeholder('-'),

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
                        'draft' => 'بانتظار الاعتماد',
                        'confirmed' => 'معتمد',
                        'cancelled' => 'ملغي',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'draft' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('reference_number')
                    ->label('المرجع')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('salesRepresentative.name')
                    ->label('المندوب')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('confirmed_at')
                    ->label('تاريخ الاعتماد')
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
                        'draft' => 'بانتظار الاعتماد',
                        'confirmed' => 'معتمد',
                        'cancelled' => 'ملغي',
                    ]),

                SelectFilter::make('customer_id')
                    ->label('العميل')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('sales_invoice_id')
                    ->label('الفاتورة')
                    ->relationship('salesInvoice', 'invoice_number')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options([
                        'cash' => 'نقدي',
                        'bank_transfer' => 'تحويل بنكي',
                        'cheque' => 'شيك',
                        'other' => 'أخرى',
                    ]),

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
                        ->label('تعديل المسودة')
                        ->modalHeading('تعديل تحصيل عميل')
                        ->slideOver()
                        ->visible(fn (CustomerPayment $record): bool => auth()->user()?->can('update', $record) === true),
                    CustomerPaymentActions::confirm(),
                    CustomerPaymentActions::cancel(),
                    CustomerPaymentActions::print(),
                    DeleteAction::make()
                        ->label('حذف المسودة')
                        ->visible(fn (CustomerPayment $record): bool => auth()->user()?->can('delete', $record) === true),
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
            ->emptyStateHeading('لا توجد تحصيلات عملاء بعد')
            ->emptyStateDescription('أنشئ أول سند تحصيل، أو غيّر عوامل التصفية إذا كنت تبحث عن عملية موجودة.');
    }
}
