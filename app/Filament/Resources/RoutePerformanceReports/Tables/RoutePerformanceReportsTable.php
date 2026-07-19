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
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\FiltersResetActionPosition;
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
                            (int) self::summaryForRecord($record, $livewire)['rank']
                    )
                    ->badge()
                    ->weight('bold')
                    ->alignCenter()
                    ->color(
                        fn (int $state): string => match ($state) {
                            1 => 'warning',
                            2 => 'gray',
                            3 => 'danger',
                            default => 'info',
                        }
                    )
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'executive')),

                TextColumn::make('name')
                    ->label('خط التوزيع')
                    ->sortable()
                    ->weight('medium')
                    ->wrap()
                    ->description(fn (DistributionRoute $record): ?string => self::routeDescription($record))
                    ->summarize(Count::make()->label('عدد الخطوط')),

                TextColumn::make('activity_report')
                    ->label('النشاط')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): bool =>
                            (bool) self::summaryForRecord($record, $livewire)['has_activity']
                    )
                    ->formatStateUsing(fn (bool $state): string => $state ? 'يوجد نشاط' : 'دون نشاط')
                    ->badge()
                    ->alignCenter()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'executive', 'collections')),

                TextColumn::make('area.name_ar')
                    ->label('المنطقة')
                    ->placeholder('-')
                    ->wrap()
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'operations')),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->placeholder('-')
                    ->alignCenter()
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'operations')),

                TextColumn::make('driver.name')
                    ->label('السائق')
                    ->placeholder('-')
                    ->wrap()
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'operations')),

                TextColumn::make('salesRepresentative.name')
                    ->label('المندوب')
                    ->placeholder('-')
                    ->wrap()
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'operations')),

                TextColumn::make('assigned_customers_report')
                    ->label('العملاء المسجلون')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): int =>
                            (int) self::summaryForRecord($record, $livewire)['assigned_active_customers']
                    )
                    ->numeric()
                    ->alignEnd()
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): int => (int) self::sum(
                                    $query,
                                    $livewire,
                                    'assigned_active_customers',
                                )
                            )
                    )
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'operations')),

                TextColumn::make('served_customers_report')
                    ->label('العملاء المخدومون')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): int =>
                            (int) self::summaryForRecord($record, $livewire)['served_customers']
                    )
                    ->numeric()
                    ->alignEnd()
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): int => (int) self::sum(
                                    $query,
                                    $livewire,
                                    'served_customers',
                                )
                            )
                    )
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'operations')),

                TextColumn::make('service_coverage_report')
                    ->label('تغطية العملاء')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): ?float =>
                            self::summaryForRecord($record, $livewire)['service_coverage_percent']
                    )
                    ->formatStateUsing(
                        fn (?float $state): string => $state === null
                            ? '-'
                            : number_format($state, 1).'%'
                    )
                    ->alignEnd()
                    ->weight('medium')
                    ->color(
                        fn (?float $state): string => match (true) {
                            $state === null => 'gray',
                            $state >= 80 => 'success',
                            $state >= 50 => 'warning',
                            default => 'danger',
                        }
                    )
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'executive', 'operations')),

                TextColumn::make('invoice_count_report')
                    ->label('عدد الفواتير')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): int =>
                            (int) self::summaryForRecord($record, $livewire)['invoice_count']
                    )
                    ->numeric()
                    ->alignEnd()
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'sales')),

                TextColumn::make('net_sales_report')
                    ->label('صافي المبيعات')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord($record, $livewire)['net_sales']
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('primary')
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float => self::sum(
                                    $query,
                                    $livewire,
                                    'net_sales',
                                )
                            )
                            ->money('SYP')
                    )
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'executive', 'sales', 'collections')),

                TextColumn::make('return_rate_report')
                    ->label('نسبة المرتجعات')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): ?float =>
                            self::summaryForRecord($record, $livewire)['return_rate_percent']
                    )
                    ->formatStateUsing(
                        fn (?float $state): string => $state === null
                            ? '-'
                            : number_format($state, 1).'%'
                    )
                    ->alignEnd()
                    ->color(fn (?float $state): string => ($state ?? 0) > 10 ? 'danger' : 'gray')
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'sales')),

                TextColumn::make('gross_profit_report')
                    ->label('الربح قبل المصاريف')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord($record, $livewire)['gross_profit']
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->color(fn (float $state): string => $state >= 0 ? 'success' : 'danger')
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'sales')),

                TextColumn::make('vehicle_expenses_report')
                    ->label('مصاريف السيارات')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord($record, $livewire)['vehicle_expenses']
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float => self::sum(
                                    $query,
                                    $livewire,
                                    'vehicle_expenses',
                                )
                            )
                            ->money('SYP')
                    )
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'sales')),

                TextColumn::make('net_contribution_report')
                    ->label('صافي المساهمة')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord($record, $livewire)['net_contribution']
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn (float $state): string => $state >= 0 ? 'success' : 'danger')
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float => self::sum(
                                    $query,
                                    $livewire,
                                    'net_contribution',
                                )
                            )
                            ->money('SYP')
                    )
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'executive', 'sales')),

                TextColumn::make('contribution_margin_report')
                    ->label('هامش المساهمة')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): ?float =>
                            self::summaryForRecord($record, $livewire)['contribution_margin_percent']
                    )
                    ->formatStateUsing(
                        fn (?float $state): string => $state === null
                            ? '-'
                            : number_format($state, 1).'%'
                    )
                    ->alignEnd()
                    ->color(fn (?float $state): string => ($state ?? 0) >= 0 ? 'success' : 'danger')
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'sales')),

                TextColumn::make('total_collections_report')
                    ->label('إجمالي المقبوضات')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord($record, $livewire)['total_collections']
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float => self::sum(
                                    $query,
                                    $livewire,
                                    'total_collections',
                                )
                            )
                            ->money('SYP')
                    )
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'executive', 'collections')),

                TextColumn::make('collection_coverage_report')
                    ->label('تغطية المقبوضات')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): ?float =>
                            self::summaryForRecord($record, $livewire)['collection_coverage_percent']
                    )
                    ->formatStateUsing(
                        fn (?float $state): string => $state === null
                            ? '-'
                            : number_format($state, 1).'%'
                    )
                    ->alignEnd()
                    ->color(
                        fn (?float $state): string => match (true) {
                            $state === null => 'gray',
                            $state >= 100 => 'success',
                            $state >= 75 => 'warning',
                            default => 'danger',
                        }
                    )
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'collections')),

                TextColumn::make('loaded_quantity_report')
                    ->label('كمية التحميل')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord($record, $livewire)['loaded_quantity']
                    )
                    ->numeric(decimalPlaces: 3)
                    ->alignEnd()
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float => self::sum(
                                    $query,
                                    $livewire,
                                    'loaded_quantity',
                                )
                            )
                            ->numeric(decimalPlaces: 3)
                    )
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'collections', 'operations')),

                TextColumn::make('cash_difference_report')
                    ->label('فرق الصندوق')
                    ->getStateUsing(
                        fn (DistributionRoute $record, $livewire): float =>
                            (float) self::summaryForRecord($record, $livewire)['cash_difference']
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn (float $state): string => abs($state) < 0.0001 ? 'success' : 'danger')
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float => self::sum(
                                    $query,
                                    $livewire,
                                    'cash_difference',
                                )
                            )
                            ->money('SYP')
                    )
                    ->visible(fn ($livewire): bool => self::isView($livewire, 'executive', 'collections')),
            ])
            ->filters([
                Filter::make('performance_settings')
                    ->label('خيارات التقرير')
                    ->schema([
                        Section::make('الفترة والترتيب')
                            ->description('حدد الفترة ومعيار الترتيب ونطاق النشاط وعدد النتائج وحالة الخط.')
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
                                    ->options(RoutePerformanceReportService::rankingMetricOptions())
                                    ->default('net_contribution')
                                    ->native(false),

                                Select::make('scope')
                                    ->label('نطاق النشاط')
                                    ->options(RoutePerformanceReportService::scopeOptions())
                                    ->default('all')
                                    ->native(false),

                                Select::make('limit')
                                    ->label('عدد النتائج')
                                    ->options(RoutePerformanceReportService::limitOptions())
                                    ->default('all')
                                    ->native(false),

                                Select::make('status')
                                    ->label('حالة الخط')
                                    ->options(RoutePerformanceReportService::statusOptions())
                                    ->default('active')
                                    ->native(false),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),

                        Section::make('نطاق التوزيع')
                            ->description('قيّد النتائج بخط أو منطقة أو سيارة محددة.')
                            ->schema([
                                Select::make('route_id')
                                    ->label('خط التوزيع')
                                    ->options(
                                        fn (): array => DistributionRoute::query()
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
                            ])
                            ->columns(3)
                            ->columnSpanFull(),

                        Section::make('فريق الخط')
                            ->description('صفِّ النتائج حسب السائق أو مندوب المبيعات المرتبط بالخط.')
                            ->schema([
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
                            ])
                            ->columns(2)
                            ->columnSpanFull(),

                        Section::make('حدود الأداء والبحث')
                            ->description('استخدم الحدود الدنيا أو البحث التفصيلي لتضييق النتائج عند الحاجة.')
                            ->schema([
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
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $settings = app(RoutePerformanceReportService::class)
                            ->normalizeSettings($data);

                        $ids = app(RoutePerformanceReportService::class)
                            ->routeIds($settings);

                        if ($ids === []) {
                            return $query->whereRaw('1 = 0');
                        }

                        $ordered = implode(',', array_map('intval', $ids));

                        return $query
                            ->whereIn('distribution_routes.id', $ids)
                            ->orderByRaw("FIELD(distribution_routes.id, {$ordered})");
                    })
                    ->default(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(1)
            ->filtersTriggerAction(
                fn (Action $action): Action => $action
                    ->button()
                    ->label('خيارات التقرير')
                    ->icon('heroicon-o-funnel')
                    ->color('gray')
            )
            ->filtersApplyAction(
                fn (Action $action): Action => $action
                    ->label('عرض النتائج')
                    ->icon('heroicon-o-magnifying-glass')
            )
            ->filtersResetActionPosition(FiltersResetActionPosition::Footer)
            ->recordActions([
                Action::make('print')
                    ->label('طباعة أداء الخط')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('طباعة تفصيل أداء خط التوزيع')
                    ->url(
                        fn (DistributionRoute $record, $livewire): string => self::printUrlFor(
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
            )
            ->persistFiltersInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->stackedOnMobile()
            ->emptyStateIcon('heroicon-o-map')
            ->emptyStateHeading('لا توجد خطوط توزيع ضمن معايير الأداء المحددة')
            ->emptyStateDescription('غيّر الفترة أو معيار الترتيب أو عوامل التصفية لعرض نتائج أخرى.');
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

    private static function isView(mixed $livewire, string ...$views): bool
    {
        $activeView = is_string($livewire->analysisView ?? null)
            ? $livewire->analysisView
            : 'executive';

        return in_array($activeView, $views, true);
    }

    private static function routeDescription(DistributionRoute $record): ?string
    {
        $parts = [];

        if (filled($record->code)) {
            $parts[] = $record->code;
        }

        if (filled($record->area?->name_ar)) {
            $parts[] = $record->area->name_ar;
        }

        return $parts === [] ? null : implode(' · ', $parts);
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
