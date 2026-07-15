<?php

namespace App\Filament\Resources\VehicleStockReports\Tables;

use App\Enums\PermissionName;
use App\Models\StockBalance;
use App\Models\Vehicle;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class VehicleStockReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('مستودع السيارة')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product.name_ar')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('batch_number')
                    ->label('رقم التشغيلة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('expiry_date')
                    ->label('تاريخ الصلاحية')
                    ->date('Y-m-d')
                    ->badge()
                    ->color(fn (mixed $state): string => self::expiryColor($state))
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('quantity')
                    ->label('الكمية الحالية')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->summarize([
                        Count::make()
                            ->label('عدد الأرصدة'),

                        Sum::make()
                            ->label('إجمالي الكمية')
                            ->numeric(decimalPlaces: 3),
                    ]),

                TextColumn::make('average_unit_cost')
                    ->label('متوسط تكلفة الوحدة')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('inventory_value')
                    ->label('قيمة المخزون')
                    ->getStateUsing(
                        fn (StockBalance $record): float =>
                            (float) $record->quantity
                            * (float) $record->average_unit_cost
                    )
                    ->money('SYP')
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('آخر تحديث')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('vehicle_id')
                    ->label('السيارة')
                    ->options(
                        fn (): array => Vehicle::query()
                            ->whereHas(
                                'warehouse',
                                fn (Builder $query): Builder => $query
                                    ->where('type', 'vehicle'),
                            )
                            ->orderBy('plate_number')
                            ->pluck('plate_number', 'id')
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, $vehicleId): Builder => $query
                                ->whereHas(
                                    'warehouse',
                                    fn (Builder $query): Builder => $query
                                        ->where('vehicle_id', $vehicleId),
                                ),
                        );
                    }),

                SelectFilter::make('product_id')
                    ->label('المنتج')
                    ->relationship('product', 'name_ar')
                    ->searchable()
                    ->preload(),

                Filter::make('expiry_date')
                    ->label('فترة الصلاحية')
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
                                    ->whereDate('expiry_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query
                                    ->whereDate('expiry_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('expiry_status')
                    ->label('حالة الصلاحية')
                    ->options([
                        'expired' => 'منتهي الصلاحية',
                        'within_30' => 'ينتهي خلال 30 يومًا',
                        'within_60' => 'ينتهي خلال 60 يومًا',
                        'without_expiry' => 'بدون تاريخ صلاحية',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['value'] ?? null;

                        return match ($status) {
                            'expired' => $query
                                ->whereNotNull('expiry_date')
                                ->whereDate('expiry_date', '<', today()),
                            'within_30' => $query
                                ->whereBetween('expiry_date', [
                                    today()->toDateString(),
                                    today()->addDays(30)->toDateString(),
                                ]),
                            'within_60' => $query
                                ->whereBetween('expiry_date', [
                                    today()->toDateString(),
                                    today()->addDays(60)->toDateString(),
                                ]),
                            'without_expiry' => $query->whereNull('expiry_date'),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                Action::make('printVehicleStock')
                    ->label('طباعة مخزون السيارة')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(
                        fn (StockBalance $record): string => route(
                            'reports.vehicle-stock.vehicle.print',
                            ['vehicle' => $record->warehouse->vehicle_id],
                        ),
                        shouldOpenInNewTab: true,
                    )
                    ->visible(
                        fn (StockBalance $record): bool =>
                            filled($record->warehouse?->vehicle_id)
                            && auth()->user()?->can(PermissionName::REPORT_VEHICLE_STOCK->value) === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->defaultSort('updated_at', 'desc');
    }

    private static function expiryColor(mixed $state): string
    {
        if (blank($state)) {
            return 'gray';
        }

        $date = $state instanceof Carbon
            ? $state
            : Carbon::parse($state);

        return match (true) {
            $date->isBefore(today()) => 'danger',
            $date->isSameDay(today()) || $date->isBefore(today()->addDays(30)) => 'warning',
            default => 'success',
        };
    }
}
