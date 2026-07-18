<?php

namespace App\Filament\Resources\RoutePerformanceReports\Tables;

use App\Enums\PermissionName;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Services\Reports\RoutePerformanceReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class RoutePerformanceReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ranking')
                    ->label('الترتيب')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): int =>
                            (int) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['rank']
                    )
                    ->badge()
                    ->color(
                        fn (int $state): string => match ($state) {
                            1 => 'warning',
                            2 => 'gray',
                            3 => 'danger',
                            default => 'info',
                        }
                    ),

                TextColumn::make('code')
                    ->label('رمز الخط')
                    ->sortable()
                    ->summarize(
                        Count::make()->label('عدد الخطوط')
                    ),

                TextColumn::make('name')
                    ->label('خط التوزيع')
                    ->sortable(),

                TextColumn::make('activity_report')
                    ->label('النشاط')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): bool =>
                            (bool) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['has_activity']
                    )
                    ->formatStateUsing(
                        fn (bool $state): string =>
                            $state ? 'يوجد نشاط' : 'دون نشاط'
                    )
                    ->badge()
                    ->color(
                        fn (bool $state): string =>
                            $state ? 'success' : 'gray'
                    ),

                TextColumn::make('area.name_ar')
                    ->label('المنطقة')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('driver.name')
                    ->label('السائق')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('salesRepresentative.name')
                    ->label('المندوب')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('assigned_customers_report')
                    ->label('العملاء المسجلون')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): int =>
                            (int) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['assigned_active_customers']
                    )
                    ->numeric()
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (
                                    QueryBuilder $query,
                                    $livewire,
                                ): int => (int) self::sum(
                                    $query,
                                    $livewire,
                                    'assigned_active_customers',
                                )
                            )
                    ),

                TextColumn::make('served_customers_report')
                    ->label('العملاء المخدومون')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): int =>
                            (int) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['served_customers']
                    )
                    ->numeric()
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (
                                    QueryBuilder $query,
                                    $livewire,
                                ): int => (int) self::sum(
                                    $query,
                                    $livewire,
                                    'served_customers',
                                )
                            )
                    ),

                TextColumn::make('service_coverage_report')
                    ->label('تغطية العملاء')
                    ->getStateUsing(
                        fn (
                            DistributionRoute $record,
                            $livewire,
                        ): ?float => self::summaryForRecord(
                            $record,
                            $livewire,
                        )['service_coverage_percent']
                    )
                    ->formatStateUsing(
                        fn (?float $state): string =>
                            $state === null
                                ? '-'
                                : number_format($state, 1).'%'
                    )
                    ->toggleable(),

                TextColumn::make('invoice_count_report')
                    ->label('الفواتير')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): int =>
                            (int) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['invoice_count']
                    )
                    ->numeric(),

                TextColumn::make('net_sales_report')
                    ->label('صافي المبيعات')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['net_sales']
                    )
                    ->money('SYP')
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (
                                    QueryBuilder $query,
                                    $livewire,
                                ): float => self::sum(
                                    $query,
                                    $livewire,
                                    'net_sales',
                                )
                            )
                            ->money('SYP')
                    ),

                TextColumn::make('return_rate_report')
                    ->label('نسبة المرتجعات')
                    ->getStateUsing(
                        fn (
                            DistributionRoute $record,
                            $livewire,
                        ): ?float => self::summaryForRecord(
                            $record,
                            $livewire,
                        )['return_rate_percent']
                    )
                    ->formatStateUsing(
                        fn (?float $state): string =>
                            $state === null
                                ? '-'
                                : number_format($state, 1).'%'
                    )
                    ->color(
                        fn (?float $state): string =>
                            ($state ?? 0) > 10 ? 'danger' : 'gray'
                    )
                    ->toggleable(),

                TextColumn::make('gross_profit_report')
                    ->label('الربح قبل المصاريف')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['gross_profit']
                    )
                    ->money('SYP')
                    ->color(
                        fn (float $state): string =>
                            $state >= 0 ? 'success' : 'danger'
                    )
                    ->toggleable(),

                TextColumn::make('vehicle_expenses_report')
                    ->label('المصاريف')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['vehicle_expenses']
                    )
                    ->money('SYP')
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (
                                    QueryBuilder $query,
                                    $livewire,
                                ): float => self::sum(
                                    $query,
                                    $livewire,
                                    'vehicle_expenses',
                                )
                            )
                            ->money('SYP')
                    ),

                TextColumn::make('net_contribution_report')
                    ->label('صافي المساهمة')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['net_contribution']
                    )
                    ->money('SYP')
                    ->weight('bold')
                    ->color(
                        fn (float $state): string =>
                            $state >= 0 ? 'success' : 'danger'
                    )
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (
                                    QueryBuilder $query,
                                    $livewire,
                                ): float => self::sum(
                                    $query,
                                    $livewire,
                                    'net_contribution',
                                )
                            )
                            ->money('SYP')
                    ),

                TextColumn::make('contribution_margin_report')
                    ->label('هامش المساهمة')
                    ->getStateUsing(
                        fn (
                            DistributionRoute $record,
                            $livewire,
                        ): ?float => self::summaryForRecord(
                            $record,
                            $livewire,
                        )['contribution_margin_percent']
                    )
                    ->formatStateUsing(
                        fn (?float $state): string =>
                            $state === null
                                ? '-'
                                : number_format($state, 1).'%'
                    )
                    ->color(
                        fn (?float $state): string =>
                            ($state ?? 0) >= 0 ? 'success' : 'danger'
                    )
                    ->toggleable(),

                TextColumn::make('total_collections_report')
                    ->label('إجمالي المقبوضات')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['total_collections']
                    )
                    ->money('SYP')
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (
                                    QueryBuilder $query,
                                    $livewire,
                                ): float => self::sum(
                                    $query,
                                    $livewire,
                                    'total_collections',
                                )
                            )
                            ->money('SYP')
                    ),

                TextColumn::make('collection_coverage_report')
                    ->label('تغطية المقبوضات')
                    ->getStateUsing(
                        fn (
                            DistributionRoute $record,
                            $livewire,
                        ): ?float => self::summaryForRecord(
                            $record,
                            $livewire,
                        )['collection_coverage_percent']
                    )
                    ->formatStateUsing(
                        fn (?float $state): string =>
                            $state === null
                                ? '-'
                                : number_format($state, 1).'%'
                    )
                    ->toggleable(),

                TextColumn::make('loaded_quantity_report')
                    ->label('كمية التحميل')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['loaded_quantity']
                    )
                    ->numeric(decimalPlaces: 3)
                    ->toggleable(),

                TextColumn::make('cash_difference_report')
                    ->label('فرق الصندوق')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['cash_difference']
                    )
                    ->money('SYP')
                    ->color(
                        fn (float $state): string =>
                            abs($state) < 0.0001 ? 'gray' : 'danger'
                    )
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('performance_settings')
                    ->label('إعدادات التقرير')
                    ->schema([
                        DatePicker::make('from')
                            ->label('من تاريخ')
                            ->default(today()->startOfMonth())
                            ->native(false),

                        DatePicker::make('until')
                            ->label('إلى تاريخ')
                            ->default(today())
                            ->native(false),

                        Select::make('ranking_metric')
                            ->label('معيار الترتيب')
                            ->options(
                                RoutePerformanceReportService::rankingMetricOptions()
                            )
                            ->default('net_contribution')
                            ->native(false),

                        Select::make('scope')
                            ->label('نطاق النشاط')
                            ->options(
                                RoutePerformanceReportService::scopeOptions()
                            )
                            ->default('all')
                            ->native(false),

                        Select::make('limit')
                            ->label('عدد النتائج')
                            ->options(
                                RoutePerformanceReportService::limitOptions()
                            )
                            ->default('all')
                            ->native(false),

                        Select::make('status')
                            ->label('حالة الخط')
                            ->options(
                                RoutePerformanceReportService::statusOptions()
                            )
                            ->default('active')
                            ->native(false),

                        Select::make('route_id')
                            ->label('خط التوزيع')
                            ->options(
                                fn (): array =>
                                    DistributionRoute::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all()
                            )
                            ->searchable()
                            ->preload(),

                        Select::make('area_id')
                            ->label('المنطقة')
                            ->options(
                                fn (): array => Area::query()
                                    ->orderBy('name_ar')
                                    ->pluck('name_ar', 'id')
                                    ->all()
                            )
                            ->searchable()
                            ->preload(),

                        Select::make('vehicle_id')
                            ->label('السيارة')
                            ->options(
                                fn (): array => Vehicle::query()
                                    ->orderBy('plate_number')
                                    ->pluck('plate_number', 'id')
                                    ->all()
                            )
                            ->searchable()
                            ->preload(),

                        Select::make('driver_id')
                            ->label('السائق')
                            ->options(
                                fn (): array => Employee::query()
                                    ->where('status', 'active')
                                    ->forOperationalRole(UserRole::DRIVER)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all()
                            )
                            ->searchable()
                            ->preload(),

                        Select::make('sales_representative_id')
                            ->label('المندوب')
                            ->options(
                                fn (): array => Employee::query()
                                    ->where('status', 'active')
                                    ->forOperationalRole(UserRole::SALES_REPRESENTATIVE)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all()
                            )
                            ->searchable()
                            ->preload(),

                        TextInput::make('minimum_net_sales')
                            ->label('الحد الأدنى لصافي المبيعات')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),

                        TextInput::make('minimum_contribution')
                            ->label('الحد الأدنى لصافي المساهمة')
                            ->numeric(),

                        TextInput::make('search')
                            ->label('بحث بالخط أو المنطقة أو السيارة أو الموظف'),
                    ])
                    ->query(function (
                        Builder $query,
                        array $data,
                    ): Builder {
                        $settings = app(
                            RoutePerformanceReportService::class
                        )->normalizeSettings($data);

                        $ids = app(
                            RoutePerformanceReportService::class
                        )->routeIds($settings);

                        if ($ids === []) {
                            return $query->whereRaw('1 = 0');
                        }

                        $ordered = implode(
                            ',',
                            array_map('intval', $ids),
                        );

                        return $query
                            ->whereIn('distribution_routes.id', $ids)
                            ->orderByRaw(
                                "FIELD(distribution_routes.id, {$ordered})"
                            );
                    })
                    ->default(),
            ])
            ->recordActions([
                Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(
                        fn (
                            DistributionRoute $record,
                            $livewire,
                        ): string => self::printUrlFor(
                            $record,
                            self::settingsFromLivewire($livewire),
                        ),
                        shouldOpenInNewTab: true,
                    )
                    ->visible(
                        fn (): bool =>
                            auth()->user()?->can(PermissionName::REPORT_ROUTE_PERFORMANCE->value) === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            );
    }

    public static function printUrlFor(
        DistributionRoute $record,
        array $settings = [],
    ): string {
        $settings = app(RoutePerformanceReportService::class)
            ->normalizeSettings($settings);

        return route('reports.route-performance.print', [
            'distributionRoute' => $record->getKey(),
            'from' => $settings['from'],
            'until' => $settings['until'],
        ]);
    }

    public static function settingsFromLivewire(mixed $livewire): array
    {
        $filters = is_array($livewire->tableFilters ?? null)
            ? $livewire->tableFilters
            : [];

        $data = is_array($filters['performance_settings'] ?? null)
            ? $filters['performance_settings']
            : [];

        return app(RoutePerformanceReportService::class)
            ->normalizeSettings($data);
    }

    private static function summaryForRecord(
        DistributionRoute $record,
        mixed $livewire,
    ): array {
        return app(RoutePerformanceReportService::class)
            ->summaryForRoute(
                routeId: (int) $record->getKey(),
                settings: self::settingsFromLivewire($livewire),
            );
    }

    private static function sum(
        QueryBuilder $query,
        mixed $livewire,
        string $field,
    ): float {
        $ids = (clone $query)
            ->reorder()
            ->pluck('distribution_routes.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return 0.0;
        }

        return (float) app(RoutePerformanceReportService::class)
            ->rankings(self::settingsFromLivewire($livewire))
            ->whereIn('route_id', $ids)
            ->sum($field);
    }
}
