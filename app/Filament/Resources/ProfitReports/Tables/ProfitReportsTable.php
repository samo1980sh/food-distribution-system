<?php

namespace App\Filament\Resources\ProfitReports\Tables;

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

class ProfitReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_type')
                    ->label('نوع الحركة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'invoice' => 'فاتورة بيع',
                        'return' => 'مرتجع بيع',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'invoice' => 'success',
                        'return' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('document_number')
                    ->label('رقم المستند')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('entry_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable()
                    ->summarize(
                        Count::make()
                            ->label('عدد الحركات')
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

                TextColumn::make('quantity')
                    ->label('صافي الكمية')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('صافي الكمية')
                            ->numeric(decimalPlaces: 3)
                    ),

                TextColumn::make('sales_amount')
                    ->label('صافي المبيعات')
                    ->money('SYP')
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('صافي المبيعات')
                            ->money('SYP')
                    ),

                TextColumn::make('cost_amount')
                    ->label('تكلفة البضاعة')
                    ->money('SYP')
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('صافي التكلفة')
                            ->money('SYP')
                    ),

                TextColumn::make('profit_amount')
                    ->label('مجمل الربح')
                    ->money('SYP')
                    ->sortable()
                    ->color(fn ($state): string => match (true) {
                        (float) $state > 0 => 'success',
                        (float) $state < 0 => 'danger',
                        default => 'gray',
                    })
                    ->summarize(
                        Sum::make()
                            ->label('مجمل الربح')
                            ->money('SYP')
                    ),

                TextColumn::make('margin_percent')
                    ->label('هامش الربح')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable()
                    ->summarize(
                        Summarizer::make()
                            ->label('هامش الربح الإجمالي')
                            ->using(function (QueryBuilder $query): float {
                                $sales = (float) (clone $query)->sum('sales_amount');
                                $profit = (float) (clone $query)->sum('profit_amount');

                                if (abs($sales) < 0.0001) {
                                    return 0;
                                }

                                return ($profit / $sales) * 100;
                            })
                            ->numeric(decimalPlaces: 2)
                            ->suffix('%')
                    ),
            ])
            ->filters([
                Filter::make('entry_date')
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
                                    ->whereDate('entry_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query
                                    ->whereDate('entry_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('entry_type')
                    ->label('نوع الحركة')
                    ->options([
                        'invoice' => 'فاتورة بيع',
                        'return' => 'مرتجع بيع',
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
            ->defaultSort('entry_date', 'desc');
    }
}
