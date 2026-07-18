<?php

namespace App\Filament\Resources\TopCustomerReports\Tables;

use App\Enums\PermissionName;
use App\Models\Area;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Services\Reports\TopCustomerReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ColumnManagerLayout;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class TopCustomerReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ranking')
                    ->label('الترتيب')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): int =>
                            (int) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['rank']
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
                    ),

                TextColumn::make('name')
                    ->label('العميل')
                    ->sortable()
                    ->weight('medium')
                    ->description(
                        fn (Customer $record): ?string =>
                            self::customerDescription($record)
                    )
                    ->wrap()
                    ->summarize(
                        Count::make()
                            ->label('عدد العملاء')
                    ),

                TextColumn::make('invoice_count_report')
                    ->label('عدد الفواتير')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): int =>
                            (int) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['invoice_count']
                    )
                    ->numeric()
                    ->alignEnd()
                    ->weight('medium')
                    ->description(
                        fn (Customer $record, $livewire): string =>
                            self::returnsCountDescription($record, $livewire)
                    )
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): int =>
                                    (int) self::summarizeQuery(
                                        $query,
                                        $livewire,
                                        'invoice_count',
                                    )
                            )
                    ),

                TextColumn::make('gross_sales_report')
                    ->label('إجمالي المبيعات')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['gross_sales']
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float =>
                                    self::summarizeQuery(
                                        $query,
                                        $livewire,
                                        'gross_sales',
                                    )
                            )
                            ->money('SYP')
                    ),

                TextColumn::make('returns_amount_report')
                    ->label('قيمة المرتجعات')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['returns_amount']
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->color('danger')
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float =>
                                    self::summarizeQuery(
                                        $query,
                                        $livewire,
                                        'returns_amount',
                                    )
                            )
                            ->money('SYP')
                    ),

                TextColumn::make('net_sales_report')
                    ->label('صافي المبيعات')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['net_sales']
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('primary')
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float =>
                                    self::summarizeQuery(
                                        $query,
                                        $livewire,
                                        'net_sales',
                                    )
                            )
                            ->money('SYP')
                    ),

                TextColumn::make('approximate_profit_report')
                    ->label('الربح التقريبي')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['approximate_profit']
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->weight('bold')
                    ->description(
                        fn (Customer $record, $livewire): string =>
                            self::profitMarginDescription($record, $livewire)
                    )
                    ->color(
                        fn (float $state): string =>
                            $state >= 0 ? 'success' : 'danger'
                    )
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float =>
                                    self::summarizeQuery(
                                        $query,
                                        $livewire,
                                        'approximate_profit',
                                    )
                            )
                            ->money('SYP')
                    ),

                TextColumn::make('net_sales_share_report')
                    ->label('الحصة من الصافي')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['net_sales_share_percent']
                    )
                    ->formatStateUsing(
                        fn (float $state): string =>
                            number_format($state, 1).'%'
                    )
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('code')
                    ->label('رمز العميل')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('area.name_ar')
                    ->label('المنطقة')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('route.name')
                    ->label('خط التوزيع')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('customer_type')
                    ->label('نوع العميل')
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            TopCustomerReportService::customerTypeLabel($state)
                    )
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('return_count_report')
                    ->label('عدد المرتجعات')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): int =>
                            (int) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['return_count']
                    )
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('net_quantity_report')
                    ->label('صافي الكمية')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['net_quantity']
                    )
                    ->numeric(decimalPlaces: 3)
                    ->alignEnd()
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float =>
                                    self::summarizeQuery(
                                        $query,
                                        $livewire,
                                        'net_quantity',
                                    )
                            )
                            ->numeric(decimalPlaces: 3)
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('average_invoice_report')
                    ->label('متوسط الفاتورة')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['average_invoice']
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('profit_margin_report')
                    ->label('هامش الربح')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): ?float =>
                            self::summaryForRecord(
                                $record,
                                $livewire,
                            )['profit_margin_percent']
                    )
                    ->formatStateUsing(
                        fn (?float $state): string =>
                            $state === null
                                ? '-'
                                : number_format($state, 1).'%'
                    )
                    ->alignEnd()
                    ->color(
                        fn (?float $state): string =>
                            ($state ?? 0) >= 0 ? 'success' : 'danger'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_purchase_date_report')
                    ->label('آخر شراء')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): ?string =>
                            self::summaryForRecord(
                                $record,
                                $livewire,
                            )['last_purchase_date']
                    )
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('ranking_settings')
                    ->label('إعدادات التقرير')
                    ->schema([
                        Section::make('الفترة والترتيب')
                            ->description('حدد الفترة، معيار الترتيب، عدد النتائج والحد الأدنى لصافي المبيعات.')
                            ->schema([
                                DatePicker::make('from')
                                    ->label('من تاريخ')
                                    ->default(today()->startOfMonth())
                                    ->native(false)
                                    ->displayFormat('Y-m-d'),

                                DatePicker::make('until')
                                    ->label('إلى تاريخ')
                                    ->default(today())
                                    ->native(false)
                                    ->displayFormat('Y-m-d'),

                                Select::make('ranking_metric')
                                    ->label('معيار الترتيب')
                                    ->options(
                                        TopCustomerReportService::rankingMetricOptions()
                                    )
                                    ->default('net_sales')
                                    ->native(false),

                                Select::make('limit')
                                    ->label('عدد النتائج')
                                    ->options(
                                        TopCustomerReportService::limitOptions()
                                    )
                                    ->default('10')
                                    ->native(false),

                                TextInput::make('minimum_net_sales')
                                    ->label('الحد الأدنى لصافي المبيعات')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),

                        Section::make('العميل ونطاق التوزيع')
                            ->description('ضيّق الترتيب حسب عميل محدد أو منطقة أو خط توزيع.')
                            ->schema([
                                Select::make('customer_id')
                                    ->label('العميل')
                                    ->options(
                                        fn (): array => Customer::query()
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
                            ])
                            ->columns(3)
                            ->columnSpanFull(),

                        Section::make('خصائص العميل والبحث')
                            ->description('صفِّ العملاء حسب النوع ونمط الدفع والحالة أو استخدم البحث التفصيلي.')
                            ->schema([
                                Select::make('customer_type')
                                    ->label('نوع العميل')
                                    ->options(
                                        TopCustomerReportService::customerTypeOptions()
                                    )
                                    ->native(false),

                                Select::make('payment_type')
                                    ->label('نمط دفع العميل')
                                    ->options(
                                        TopCustomerReportService::paymentTypeOptions()
                                    )
                                    ->native(false),

                                Select::make('status')
                                    ->label('حالة العميل')
                                    ->options(
                                        TopCustomerReportService::statusOptions()
                                    )
                                    ->native(false),

                                TextInput::make('search')
                                    ->label('بحث بالعميل أو الهاتف أو المنطقة أو الخط'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $settings = app(TopCustomerReportService::class)
                            ->normalizeSettings($data);

                        $ids = app(TopCustomerReportService::class)
                            ->customerIds($settings);

                        if ($ids === []) {
                            return $query->whereRaw('1 = 0');
                        }

                        $orderedIds = implode(
                            ',',
                            array_map('intval', $ids),
                        );

                        return $query
                            ->whereIn('customers.id', $ids)
                            ->orderByRaw(
                                "FIELD(customers.id, {$orderedIds})"
                            );
                    })
                    ->default(),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(1)
            ->filtersTriggerAction(
                fn (Action $action): Action => $action
                    ->button()
                    ->label('خيارات التقرير')
                    ->icon('heroicon-o-funnel')
                    ->color('gray')
                    ->modalHeading('خيارات تصفية تقرير العملاء الأكثر شراءً')
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
                    ->modalHeading('إدارة أعمدة تقرير العملاء الأكثر شراءً')
                    ->modalWidth(Width::ThreeExtraLarge),
            )
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->recordActions([
                Action::make('print')
                    ->label('طباعة تفاصيل المشتريات')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('طباعة تفاصيل مشتريات العميل')
                    ->url(
                        fn (Customer $record, $livewire): string =>
                            self::printUrlFor(
                                $record,
                                self::settingsFromLivewire($livewire),
                            ),
                        shouldOpenInNewTab: true,
                    )
                    ->visible(
                        fn (): bool =>
                            auth()->user()?->can(PermissionName::REPORT_TOP_CUSTOMERS->value) === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->stackedOnMobile()
            ->emptyStateIcon('heroicon-o-trophy')
            ->emptyStateHeading('لا يوجد عملاء ضمن معايير الترتيب المحددة')
            ->emptyStateDescription('غيّر الفترة أو معيار الترتيب أو عوامل التصفية لعرض نتائج أخرى.');
    }

    private static function customerDescription(Customer $record): ?string
    {
        $parts = [];

        if (filled($record->code)) {
            $parts[] = 'الرمز: '.$record->code;
        }

        if (filled($record->area?->name_ar)) {
            $parts[] = 'المنطقة: '.$record->area->name_ar;
        }

        if (filled($record->route?->name)) {
            $parts[] = 'الخط: '.$record->route->name;
        }

        return $parts === [] ? null : implode(' • ', $parts);
    }

    private static function returnsCountDescription(
        Customer $record,
        mixed $livewire,
    ): string {
        $returnCount = (int) self::summaryForRecord(
            $record,
            $livewire,
        )['return_count'];

        return 'المرتجعات: '.number_format($returnCount);
    }

    private static function profitMarginDescription(
        Customer $record,
        mixed $livewire,
    ): string {
        $margin = self::summaryForRecord(
            $record,
            $livewire,
        )['profit_margin_percent'];

        return $margin === null
            ? 'الهامش: -'
            : 'الهامش: '.number_format((float) $margin, 1).'%';
    }

    public static function printUrlFor(
        Customer $record,
        array $settings = [],
    ): string {
        $settings = app(TopCustomerReportService::class)
            ->normalizeSettings($settings);

        return route('reports.top-customers.print', [
            'customer' => $record->getKey(),
            'from' => $settings['from'],
            'until' => $settings['until'],
        ]);
    }

    public static function settingsFromLivewire(mixed $livewire): array
    {
        $filters = is_array($livewire->tableFilters ?? null)
            ? $livewire->tableFilters
            : [];

        $data = is_array($filters['ranking_settings'] ?? null)
            ? $filters['ranking_settings']
            : [];

        return app(TopCustomerReportService::class)
            ->normalizeSettings($data);
    }

    private static function summaryForRecord(
        Customer $record,
        mixed $livewire,
    ): array {
        return app(TopCustomerReportService::class)
            ->summaryForCustomer(
                customerId: (int) $record->getKey(),
                settings: self::settingsFromLivewire($livewire),
            );
    }

    private static function summarizeQuery(
        QueryBuilder $query,
        mixed $livewire,
        string $field,
    ): float {
        $ids = (clone $query)
            ->reorder()
            ->pluck('customers.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return 0.0;
        }

        return (float) app(TopCustomerReportService::class)
            ->rankings(self::settingsFromLivewire($livewire))
            ->whereIn('customer_id', $ids)
            ->sum($field);
    }
}
