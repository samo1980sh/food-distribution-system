<?php

namespace App\Filament\Resources\SalesReports\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('رقم الفاتورة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable()
                    ->summarize(
                        Count::make()
                            ->label('عدد الفواتير')
                    ),

                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->searchable()
                    ->sortable(),

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
                    ->label('المندوب')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('payment_type')
                    ->label('طريقة الدفع')
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

                TextColumn::make('subtotal')
                    ->label('مجموع المواد')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable()
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('discount_amount')
                    ->label('الحسم')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable()
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('tax_amount')
                    ->label('الضريبة / الإضافات')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('total_amount')
                    ->label('إجمالي الفاتورة')
                    ->money('SYP')
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('invoice_cash_amount')
                    ->label('نقد الفاتورة')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('paid_amount')
                    ->label('المدفوع')
                    ->money('SYP')
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('remaining_amount')
                    ->label('المتبقي')
                    ->money('SYP')
                    ->sortable()
                    ->color(
                        fn ($state): string => ((float) $state) > 0
                            ? 'warning'
                            : 'success'
                    )
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
            ])
            ->filters([
                Filter::make('invoice_date')
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
                                    ->whereDate('invoice_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query
                                    ->whereDate('invoice_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'confirmed' => 'معتمدة',
                        'cancelled' => 'ملغاة',
                    ]),

                SelectFilter::make('payment_type')
                    ->label('طريقة الدفع')
                    ->options([
                        'cash' => 'نقدي',
                        'credit' => 'آجل',
                        'partial' => 'جزئي',
                    ]),

                SelectFilter::make('customer_id')
                    ->label('العميل')
                    ->relationship('customer', 'name')
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
            ->recordActions([])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->defaultSort('invoice_date', 'desc');
    }
}