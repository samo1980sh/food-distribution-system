<?php

namespace App\Filament\Resources\ExpiryRiskReports\Tables;

use App\Enums\PermissionName;
use App\Models\ProductCategory;
use App\Models\StockBalance;
use App\Models\Vehicle;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExpiryRiskReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.type')
                    ->label('نوع المستودع')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => self::warehouseTypeLabel($state)
                    )
                    ->color(fn (?string $state): string => match ($state) {
                        'main' => 'primary',
                        'branch' => 'info',
                        'vehicle' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('warehouse.vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product.name_ar')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('product.category.name_ar')
                    ->label('التصنيف')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('product.unit.name_ar')
                    ->label('الوحدة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('batch_number')
                    ->label('رقم التشغيلة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('expiry_date')
                    ->label('تاريخ الصلاحية')
                    ->date('Y-m-d')
                    ->placeholder('غير مسجل')
                    ->sortable(),

                TextColumn::make('expiry_status')
                    ->label('حالة الصلاحية')
                    ->getStateUsing(
                        fn (StockBalance $record): string =>
                            self::expiryStatus($record->expiry_date)
                    )
                    ->formatStateUsing(
                        fn (string $state): string =>
                            self::expiryStatusLabel($state)
                    )
                    ->badge()
                    ->color(
                        fn (string $state): string =>
                            self::expiryStatusColor($state)
                    ),

                TextColumn::make('days_remaining')
                    ->label('الأيام المتبقية')
                    ->getStateUsing(
                        fn (StockBalance $record): ?int =>
                            self::daysRemaining($record->expiry_date)
                    )
                    ->formatStateUsing(
                        fn (?int $state): string =>
                            self::daysRemainingLabel($state)
                    ),

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
                    ->label('القيمة بالتكلفة')
                    ->getStateUsing(
                        fn (StockBalance $record): float =>
                            self::inventoryValue($record)
                    )
                    ->money('SYP')
                    ->summarize([
                        Summarizer::make()
                            ->label('إجمالي القيمة')
                            ->using(
                                fn (QueryBuilder $query): float =>
                                    (float) (clone $query)
                                        ->sum(DB::raw('quantity * average_unit_cost'))
                            )
                            ->money('SYP'),
                    ]),

                TextColumn::make('updated_at')
                    ->label('آخر تحديث')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('expiry_risk')
                    ->label('نطاق الصلاحية')
                    ->schema([
                        Select::make('scope')
                            ->label('النطاق')
                            ->options(self::expiryScopeOptions())
                            ->default('risk_30')
                            ->native(false),

                        Select::make('status')
                            ->label('الحالة الدقيقة')
                            ->options(self::expiryStatusOptions())
                            ->native(false),

                        DatePicker::make('from')
                            ->label('من تاريخ صلاحية')
                            ->native(false)
                            ->displayFormat('Y-m-d'),

                        DatePicker::make('until')
                            ->label('إلى تاريخ صلاحية')
                            ->native(false)
                            ->displayFormat('Y-m-d'),
                    ])
                    ->query(
                        fn (Builder $query, array $data): Builder =>
                            self::applyExpiryFilter($query, $data)
                    ),

                SelectFilter::make('warehouse_id')
                    ->label('المستودع')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('warehouse_type')
                    ->label('نوع المستودع')
                    ->options(self::warehouseTypeOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, string $type): Builder => $query
                                ->whereHas(
                                    'warehouse',
                                    fn (Builder $query): Builder => $query
                                        ->where('type', $type),
                                ),
                        );
                    }),

                SelectFilter::make('vehicle_id')
                    ->label('السيارة')
                    ->options(
                        fn (): array => Vehicle::query()
                            ->whereHas('warehouse')
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

                SelectFilter::make('category_id')
                    ->label('التصنيف')
                    ->options(
                        fn (): array => ProductCategory::query()
                            ->orderBy('name_ar')
                            ->pluck('name_ar', 'id')
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, $categoryId): Builder => $query
                                ->whereHas(
                                    'product',
                                    fn (Builder $query): Builder => $query
                                        ->where('category_id', $categoryId),
                                ),
                        );
                    }),

                Filter::make('batch_number')
                    ->label('رقم التشغيلة')
                    ->schema([
                        TextInput::make('value')
                            ->label('رقم التشغيلة'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            filled($data['value'] ?? null),
                            fn (Builder $query): Builder => $query
                                ->where(
                                    'batch_number',
                                    'like',
                                    '%'.trim((string) $data['value']).'%',
                                ),
                        );
                    }),
            ])
            ->recordActions([
                Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(
                        fn (StockBalance $record): string => self::printUrlFor($record),
                        shouldOpenInNewTab: true,
                    )
                    ->visible(
                        fn (): bool =>
                            auth()->user()?->can(PermissionName::REPORT_EXPIRY_RISK->value) === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->defaultSort('expiry_date');
    }

    public static function printUrlFor(StockBalance $record): string
    {
        return route('reports.expiry-risk.print', [
            'stockBalance' => $record->getKey(),
        ]);
    }

    public static function applyExpiryFilter(
        Builder $query,
        array $data,
    ): Builder {
        $status = filled($data['status'] ?? null)
            ? (string) $data['status']
            : null;

        $from = filled($data['from'] ?? null)
            ? (string) $data['from']
            : null;

        $until = filled($data['until'] ?? null)
            ? (string) $data['until']
            : null;

        if ($status !== null) {
            return self::applyExpiryStatus($query, $status);
        }

        if ($from !== null || $until !== null) {
            return $query
                ->whereNotNull('expiry_date')
                ->when(
                    $from,
                    fn (Builder $query, string $date): Builder => $query
                        ->whereDate('expiry_date', '>=', $date),
                )
                ->when(
                    $until,
                    fn (Builder $query, string $date): Builder => $query
                        ->whereDate('expiry_date', '<=', $date),
                );
        }

        return self::applyExpiryScope(
            $query,
            filled($data['scope'] ?? null)
                ? (string) $data['scope']
                : 'risk_30',
        );
    }

    public static function applyExpiryScope(
        Builder $query,
        string $scope,
    ): Builder {
        return match ($scope) {
            'expired_only' => self::applyExpiryStatus($query, 'expired'),
            'today' => self::applyExpiryStatus($query, 'today'),
            'within_7' => $query
                ->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [
                    today()->toDateString(),
                    today()->addDays(7)->toDateString(),
                ]),
            'within_15' => $query
                ->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [
                    today()->toDateString(),
                    today()->addDays(15)->toDateString(),
                ]),
            'within_30' => $query
                ->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [
                    today()->toDateString(),
                    today()->addDays(30)->toDateString(),
                ]),
            'within_60' => $query
                ->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [
                    today()->toDateString(),
                    today()->addDays(60)->toDateString(),
                ]),
            'within_90' => $query
                ->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [
                    today()->toDateString(),
                    today()->addDays(90)->toDateString(),
                ]),
            'missing' => self::applyExpiryStatus($query, 'missing'),
            'all' => $query,
            default => $query->where(
                function (Builder $query): void {
                    $query
                        ->whereNull('expiry_date')
                        ->orWhereDate(
                            'expiry_date',
                            '<=',
                            today()->addDays(30),
                        );
                },
            ),
        };
    }

    public static function applyExpiryStatus(
        Builder $query,
        string $status,
    ): Builder {
        return match ($status) {
            'missing' => $query->whereNull('expiry_date'),
            'expired' => $query
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', today()),
            'today' => $query
                ->whereDate('expiry_date', today()),
            'critical_7' => $query
                ->whereBetween('expiry_date', [
                    today()->addDay()->toDateString(),
                    today()->addDays(7)->toDateString(),
                ]),
            'near_30' => $query
                ->whereBetween('expiry_date', [
                    today()->addDays(8)->toDateString(),
                    today()->addDays(30)->toDateString(),
                ]),
            'monitoring_60' => $query
                ->whereBetween('expiry_date', [
                    today()->addDays(31)->toDateString(),
                    today()->addDays(60)->toDateString(),
                ]),
            'valid' => $query
                ->whereDate('expiry_date', '>', today()->addDays(60)),
            default => $query,
        };
    }

    public static function expiryStatus(mixed $expiryDate): string
    {
        if (blank($expiryDate)) {
            return 'missing';
        }

        $date = $expiryDate instanceof Carbon
            ? $expiryDate->copy()->startOfDay()
            : Carbon::parse($expiryDate)->startOfDay();

        $days = today()->diffInDays($date, false);

        return match (true) {
            $days < 0 => 'expired',
            $days === 0 => 'today',
            $days <= 7 => 'critical_7',
            $days <= 30 => 'near_30',
            $days <= 60 => 'monitoring_60',
            default => 'valid',
        };
    }

    public static function daysRemaining(mixed $expiryDate): ?int
    {
        if (blank($expiryDate)) {
            return null;
        }

        $date = $expiryDate instanceof Carbon
            ? $expiryDate->copy()->startOfDay()
            : Carbon::parse($expiryDate)->startOfDay();

        return (int) today()->diffInDays($date, false);
    }

    public static function daysRemainingLabel(?int $days): string
    {
        return match (true) {
            $days === null => 'غير مسجل',
            $days < 0 => 'متجاوز بـ '.abs($days).' يوم',
            $days === 0 => 'ينتهي اليوم',
            default => $days.' يوم',
        };
    }

    public static function expiryStatusOptions(): array
    {
        return [
            'missing' => 'تاريخ الصلاحية غير مسجل',
            'expired' => 'منتهي الصلاحية',
            'today' => 'ينتهي اليوم',
            'critical_7' => 'حرج: خلال 7 أيام',
            'near_30' => 'قريب: خلال 30 يومًا',
            'monitoring_60' => 'تحت المراقبة: خلال 60 يومًا',
            'valid' => 'صالح لأكثر من 60 يومًا',
        ];
    }

    public static function expiryScopeOptions(): array
    {
        return [
            'risk_30' => 'المفقود والمنتهي وما ينتهي خلال 30 يومًا',
            'expired_only' => 'المنتهي فقط',
            'today' => 'ينتهي اليوم',
            'within_7' => 'خلال 7 أيام',
            'within_15' => 'خلال 15 يومًا',
            'within_30' => 'خلال 30 يومًا',
            'within_60' => 'خلال 60 يومًا',
            'within_90' => 'خلال 90 يومًا',
            'missing' => 'تاريخ الصلاحية غير مسجل',
            'all' => 'جميع الأرصدة التي تتطلب صلاحية',
        ];
    }

    public static function expiryStatusLabel(string $status): string
    {
        return self::expiryStatusOptions()[$status] ?? $status;
    }

    public static function expiryStatusColor(string $status): string
    {
        return match ($status) {
            'missing', 'expired' => 'danger',
            'today', 'critical_7' => 'warning',
            'near_30' => 'info',
            'monitoring_60' => 'gray',
            'valid' => 'success',
            default => 'gray',
        };
    }

    public static function warehouseTypeOptions(): array
    {
        return [
            'main' => 'رئيسي',
            'branch' => 'فرعي',
            'vehicle' => 'سيارة',
        ];
    }

    public static function warehouseTypeLabel(?string $type): string
    {
        return self::warehouseTypeOptions()[$type] ?? ($type ?: '-');
    }

    public static function inventoryValue(StockBalance $record): float
    {
        return (float) $record->quantity
            * (float) $record->average_unit_cost;
    }
}
