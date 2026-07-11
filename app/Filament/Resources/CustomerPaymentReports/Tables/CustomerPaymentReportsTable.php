<?php

namespace App\Filament\Resources\CustomerPaymentReports\Tables;

use App\Models\CustomerPayment;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
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
                    ->sortable(),

                TextColumn::make('payment_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable()
                    ->summarize(
                        Count::make()
                            ->label('عدد التحصيلات')
                    ),

                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('salesInvoice.invoice_number')
                    ->label('الفاتورة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('route.name')
                    ->label('خط التوزيع')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('salesRepresentative.name')
                    ->label('مندوب التحصيل')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

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
                    }),

                TextColumn::make('amount')
                    ->label('إجمالي التحصيل')
                    ->money('SYP')
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('cash_amount')
                    ->label('التحصيل النقدي')
                    ->state(
                        fn (CustomerPayment $record): float => $record->payment_method === 'cash'
                            ? (float) $record->amount
                            : 0
                    )
                    ->money('SYP')
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
                    ->toggleable(),

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
            ])
            ->recordActions([
                Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(
                        fn (CustomerPayment $record): string => route(
                            'reports.customer-payments.print',
                            $record,
                        ),
                        shouldOpenInNewTab: true,
                    )
                    ->visible(
                        fn (): bool => auth()->user()?->canManageSalesAndCollections() === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->defaultSort('payment_date', 'desc');
    }
}