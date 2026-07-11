<?php

namespace App\Filament\Resources\VehicleLoadReports\Tables;

use App\Models\VehicleLoad;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehicleLoadReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('load_number')
                    ->label('رقم أمر التحميل')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('load_date')
                    ->label('تاريخ التحميل')
                    ->date('Y-m-d')
                    ->sortable()
                    ->summarize(
                        Count::make()
                            ->label('عدد أوامر التحميل')
                    ),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('route.name')
                    ->label('خط التوزيع')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('driver.name')
                    ->label('السائق')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('salesRepresentative.name')
                    ->label('مندوب المبيعات')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('fromWarehouse.name')
                    ->label('المستودع المصدر')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('toWarehouse.name')
                    ->label('مستودع السيارة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('عدد المواد')
                    ->counts('items')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_quantity')
                    ->label('إجمالي الكمية')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('إجمالي الكميات')
                            ->numeric(decimalPlaces: 3)
                    ),

                TextColumn::make('total_cost')
                    ->label('إجمالي التكلفة')
                    ->money('SYP')
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('إجمالي التكاليف')
                            ->money('SYP')
                    ),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'مسودة',
                        'approved' => 'معتمد',
                        'cancelled' => 'ملغي',
                        'closed' => 'مغلق',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'draft' => 'warning',
                        'approved' => 'success',
                        'cancelled' => 'danger',
                        'closed' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('approved_at')
                    ->label('تاريخ الاعتماد')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('load_date')
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
                                    ->whereDate('load_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query
                                    ->whereDate('load_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'approved' => 'معتمد',
                        'cancelled' => 'ملغي',
                        'closed' => 'مغلق',
                    ]),

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

                SelectFilter::make('driver_id')
                    ->label('السائق')
                    ->relationship('driver', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('sales_representative_id')
                    ->label('مندوب المبيعات')
                    ->relationship('salesRepresentative', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('from_warehouse_id')
                    ->label('المستودع المصدر')
                    ->relationship('fromWarehouse', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('to_warehouse_id')
                    ->label('مستودع السيارة')
                    ->relationship('toWarehouse', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(
                        fn (VehicleLoad $record): string => route(
                            'reports.vehicle-loads.print',
                            $record,
                        ),
                        shouldOpenInNewTab: true,
                    )
                    ->visible(
                        fn (): bool => auth()->user()?->canManageDistribution() === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->defaultSort('load_date', 'desc');
    }
}
