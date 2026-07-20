<?php

namespace App\Filament\Resources\SalesInvoices\Tables;

use App\Enums\OperationSource;
use App\Filament\Resources\SalesInvoices\Actions\SalesInvoiceActions;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\SalesInvoice;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (SalesInvoice $record): string => SalesInvoiceResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('رقم الفاتورة')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('operation_source')
                    ->label('مصدر العملية')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => OperationSource::labelFor($state))
                    ->color(fn (mixed $state): string => OperationSource::colorFor($state))
                    ->description(fn (SalesInvoice $record): ?string => $record->creator?->name)
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->description(fn (SalesInvoice $record): ?string => $record->route?->name),

                TextColumn::make('invoice_date')
                    ->label('تاريخ الفاتورة')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('الاستحقاق')
                    ->date('Y-m-d')
                    ->sortable()
                    ->color(fn ($state, SalesInvoice $record): string =>
                        $record->status === 'confirmed'
                        && (float) $record->remaining_amount > 0
                        && $record->due_date?->isPast()
                            ? 'danger'
                            : 'gray'
                    ),

                TextColumn::make('total_amount')
                    ->label('الإجمالي')
                    ->money('SYP')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('remaining_amount')
                    ->label('المتبقي')
                    ->money('SYP')
                    ->sortable()
                    ->color(fn ($state): string => ((float) $state) > 0 ? 'warning' : 'success'),

                TextColumn::make('payment_type')
                    ->label('الدفع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash' => 'نقدي',
                        'credit' => 'آجل',
                        'partial' => 'جزئي',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'cash' => 'success',
                        'credit' => 'warning',
                        'partial' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'بانتظار الاعتماد',
                        'confirmed' => 'معتمدة',
                        'cancelled' => 'ملغاة',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'draft' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('warehouse.name')
                    ->label('مستودع البيع')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('salesRepresentative.name')
                    ->label('المندوب')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('paid_amount')
                    ->label('المدفوع')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('credit_limit_overridden')
                    ->label('استثناء ائتماني')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'تم التجاوز' : 'لا')
                    ->color(fn (bool $state): string => $state ? 'danger' : 'gray')
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
                        'confirmed' => 'معتمدة',
                        'cancelled' => 'ملغاة',
                    ]),

                SelectFilter::make('payment_type')
                    ->label('طريقة الدفع')
                    ->options([
                        'cash' => 'نقدي',
                        'credit' => 'آجل',
                        'partial' => 'دفعة جزئية',
                    ]),

                SelectFilter::make('customer_id')
                    ->label('العميل')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

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
                    ViewAction::make()->label('عرض التفاصيل'),
                    EditAction::make()
                        ->label('تعديل')
                        ->visible(fn (SalesInvoice $record): bool => auth()->user()?->can('update', $record) === true),
                    SalesInvoiceActions::confirm(),
                    SalesInvoiceActions::cancel(),
                    SalesInvoiceActions::print(),
                    DeleteAction::make()
                        ->label('حذف المسودة')
                        ->visible(fn (SalesInvoice $record): bool => auth()->user()?->can('delete', $record) === true),
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
            ->emptyStateIcon('heroicon-o-receipt-percent')
            ->emptyStateHeading('لا توجد فواتير بيع بعد')
            ->emptyStateDescription('لم تصل فواتير للمراجعة بعد. استخدم الإدخال الإداري فقط للحالات الاستثنائية.');
    }
}
