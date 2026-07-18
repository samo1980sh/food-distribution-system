<?php

namespace App\Filament\Resources\CustomerPaymentReports\Tables;

use App\Enums\PermissionName;
use App\Models\CustomerPayment;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ColumnManagerLayout;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class CustomerPaymentReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_number')
                    ->label('رقم التحصيل')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('تم نسخ رقم التحصيل'),

                TextColumn::make('payment_date')
                    ->label('تاريخ التحصيل')
                    ->date('Y-m-d')
                    ->sortable()
                    ->description(
                        fn (CustomerPayment $record): ?string => $record->confirmed_at
                            ? 'الاعتماد: '.$record->confirmed_at->format('Y-m-d H:i')
                            : null,
                    )
                    ->summarize(
                        Count::make()
                            ->label('عدد التحصيلات')
                    ),

                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make('salesInvoice.invoice_number')
                    ->label('الفاتورة')
                    ->searchable()
                    ->placeholder('-'),

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
                        'other' => 'gray',
                        default => 'gray',
                    })
                    ->description(
                        fn (CustomerPayment $record): ?string => filled($record->reference_number)
                            ? 'المرجع: '.$record->reference_number
                            : null,
                    ),

                TextColumn::make('amount')
                    ->label('قيمة التحصيل')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'مسودة',
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

                TextColumn::make('route.name')
                    ->label('خط التوزيع')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('salesRepresentative.name')
                    ->label('مندوب التحصيل')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cash_amount')
                    ->label('التحصيل النقدي')
                    ->state(
                        fn (CustomerPayment $record): float => $record->payment_method === 'cash'
                            ? (float) $record->amount
                            : 0
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي النقدي')
                            ->using(
                                fn (QueryBuilder $query): float => (float) $query
                                    ->where('payment_method', 'cash')
                                    ->sum('amount')
                            )
                            ->money('SYP')
                    ),

                TextColumn::make('non_cash_amount')
                    ->label('التحصيل غير النقدي')
                    ->state(
                        fn (CustomerPayment $record): float => $record->payment_method !== 'cash'
                            ? (float) $record->amount
                            : 0
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي غير النقدي')
                            ->using(
                                fn (QueryBuilder $query): float => (float) $query
                                    ->whereIn('payment_method', [
                                        'bank_transfer',
                                        'cheque',
                                        'other',
                                    ])
                                    ->sum('amount')
                            )
                            ->money('SYP')
                    ),

                TextColumn::make('reference_number')
                    ->label('رقم المرجع / الشيك')
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
                Filter::make('payment_date')
                    ->label('الفترة')
                    ->schema([
                        DatePicker::make('from')
                            ->label('من تاريخ')
                            ->native(false)
                            ->displayFormat('Y-m-d'),

                        DatePicker::make('until')
                            ->label('إلى تاريخ')
                            ->native(false)
                            ->displayFormat('Y-m-d'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder => $query
                                    ->whereDate('payment_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query
                                    ->whereDate('payment_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'confirmed' => 'معتمد',
                        'cancelled' => 'ملغي',
                    ]),

                SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options([
                        'cash' => 'نقدي',
                        'bank_transfer' => 'تحويل بنكي',
                        'cheque' => 'شيك',
                        'other' => 'أخرى',
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

                SelectFilter::make('warehouse_id')
                    ->label('المستودع')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('route_id')
                    ->label('خط التوزيع')
                    ->relationship('route', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('sales_representative_id')
                    ->label('المندوب')
                    ->relationship('salesRepresentative', 'name')
                    ->searchable()
                    ->preload(),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersFormSchema(fn (array $filters): array => [
                Section::make('الفترة والحالة')
                    ->description('حدد الفترة الزمنية وحالة التحصيل وطريقة الدفع المطلوبة.')
                    ->schema([
                        $filters['payment_date'],
                        $filters['status'],
                        $filters['payment_method'],
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('العميل ونطاق التشغيل')
                    ->description('ضيّق النتائج حسب العميل أو الفاتورة أو المستودع أو السيارة أو خط التوزيع أو المندوب.')
                    ->schema([
                        $filters['customer_id'],
                        $filters['sales_invoice_id'],
                        $filters['warehouse_id'],
                        $filters['vehicle_id'],
                        $filters['route_id'],
                        $filters['sales_representative_id'],
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ])
            ->filtersTriggerAction(
                fn (Action $action): Action => $action
                    ->button()
                    ->label('خيارات التقرير')
                    ->icon('heroicon-o-funnel')
                    ->color('gray')
                    ->modalHeading('خيارات تصفية تقرير التحصيلات')
                    ->modalWidth(Width::FiveExtraLarge),
            )
            ->filtersApplyAction(
                fn (Action $action): Action => $action
                    ->label('عرض النتائج')
                    ->icon('heroicon-o-magnifying-glass'),
            )
            ->filtersResetActionPosition(FiltersResetActionPosition::Footer)
            ->columnManagerLayout(ColumnManagerLayout::Modal)
            ->columnManagerColumns(2)
            ->columnManagerTriggerAction(
                fn (Action $action): Action => $action
                    ->button()
                    ->label('الأعمدة')
                    ->icon('heroicon-o-view-columns')
                    ->color('gray')
                    ->modalHeading('إدارة أعمدة تقرير التحصيلات')
                    ->modalWidth(Width::ThreeExtraLarge),
            )
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->recordActions([
                Action::make('print')
                    ->label('طباعة سند التحصيل')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('طباعة سند التحصيل')
                    ->url(
                        fn (CustomerPayment $record): string => route(
                            'reports.customer-payments.print',
                            $record,
                        ),
                        shouldOpenInNewTab: true,
                    )
                    ->visible(
                        fn (): bool => auth()->user()?->can(PermissionName::REPORT_CUSTOMER_PAYMENTS->value) === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->defaultSort('payment_date', 'desc')
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->stackedOnMobile()
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading('لا توجد نتائج في تقرير التحصيلات')
            ->emptyStateDescription('غيّر خيارات التقرير أو أزل عوامل التصفية الحالية لعرض تحصيلات أخرى.');
    }
}
