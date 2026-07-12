<?php

namespace App\Filament\Resources\OverdueCustomerReports\Tables;

use App\Models\Customer;
use App\Services\Reports\OverdueCustomerReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Throwable;

class OverdueCustomerReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('رمز العميل')
                    ->searchable()
                    ->sortable()
                    ->summarize(
                        Count::make()
                            ->label('عدد العملاء')
                    ),

                TextColumn::make('name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('area.name_ar')
                    ->label('المنطقة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('route.name')
                    ->label('خط التوزيع')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('contact_phone')
                    ->label('الهاتف')
                    ->getStateUsing(
                        fn (Customer $record): string =>
                            $record->mobile ?: ($record->phone ?: '-')
                    )
                    ->searchable(['phone', 'mobile'])
                    ->toggleable(),

                TextColumn::make('payment_type')
                    ->label('نوع الدفع')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            OverdueCustomerReportService::paymentTypeLabel($state)
                    )
                    ->color(fn (?string $state): string => match ($state) {
                        'cash' => 'success',
                        'credit' => 'warning',
                        'partial' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('credit_limit')
                    ->label('الحد الائتماني')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('current_balance_report')
                    ->label('الرصيد الحالي')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['current_balance']
                    )
                    ->money('SYP')
                    ->summarize(
                        Summarizer::make()
                            ->label('إجمالي الرصيد')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float =>
                                    self::summarizeQuery(
                                        $query,
                                        $livewire,
                                        'current_balance',
                                    )
                            )
                            ->money('SYP')
                    ),

                TextColumn::make('overdue_amount_report')
                    ->label('المبلغ المتأخر')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['overdue_amount']
                    )
                    ->money('SYP')
                    ->summarize(
                        Summarizer::make()
                            ->label('إجمالي المتأخر')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float =>
                                    self::summarizeQuery(
                                        $query,
                                        $livewire,
                                        'overdue_amount',
                                    )
                            )
                            ->money('SYP')
                    ),

                TextColumn::make('not_due_amount_report')
                    ->label('غير المتأخر')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): float =>
                            (float) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['not_due_amount']
                    )
                    ->money('SYP')
                    ->summarize(
                        Summarizer::make()
                            ->label('إجمالي غير المتأخر')
                            ->using(
                                fn (QueryBuilder $query, $livewire): float =>
                                    self::summarizeQuery(
                                        $query,
                                        $livewire,
                                        'not_due_amount',
                                    )
                            )
                            ->money('SYP')
                    ),

                TextColumn::make('overdue_invoices_count_report')
                    ->label('الفواتير المتأخرة')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): int =>
                            (int) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['overdue_invoices_count']
                    )
                    ->numeric(),

                TextColumn::make('oldest_overdue_date_report')
                    ->label('أقدم مديونية متأخرة')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): ?string =>
                            self::summaryForRecord(
                                $record,
                                $livewire,
                            )['oldest_overdue_date']
                    )
                    ->placeholder('-'),

                TextColumn::make('days_overdue_report')
                    ->label('أيام التأخير')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): int =>
                            (int) self::summaryForRecord(
                                $record,
                                $livewire,
                            )['days_overdue']
                    )
                    ->formatStateUsing(
                        fn (int $state): string =>
                            $state > 0 ? $state.' يوم' : '-'
                    ),

                TextColumn::make('credit_usage_report')
                    ->label('استخدام الحد')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): ?float =>
                            self::summaryForRecord(
                                $record,
                                $livewire,
                            )['credit_usage_percent']
                    )
                    ->formatStateUsing(
                        fn (?float $state): string =>
                            $state === null
                                ? 'لا يوجد حد'
                                : number_format($state, 1).'%'
                    )
                    ->toggleable(),

                TextColumn::make('risk_status_report')
                    ->label('المخاطر')
                    ->getStateUsing(
                        fn (Customer $record, $livewire): string =>
                            self::summaryForRecord(
                                $record,
                                $livewire,
                            )['risk_status']
                    )
                    ->formatStateUsing(
                        fn (string $state): string =>
                            OverdueCustomerReportService::riskLabel($state)
                    )
                    ->badge()
                    ->color(
                        fn (string $state): string =>
                            OverdueCustomerReportService::riskColor($state)
                    ),
            ])
            ->filters([
                Filter::make('overdue_settings')
                    ->label('إعدادات التأخير')
                    ->schema([
                        Select::make('scope')
                            ->label('نطاق التقرير')
                            ->options([
                                'overdue' => 'العملاء المتأخرون فقط',
                                'all_positive' => 'جميع العملاء ذوي الرصيد الموجب',
                            ])
                            ->default('overdue')
                            ->native(false),

                        Select::make('credit_days')
                            ->label('مدة السماح')
                            ->options([
                                7 => '7 أيام',
                                15 => '15 يومًا',
                                30 => '30 يومًا',
                                45 => '45 يومًا',
                                60 => '60 يومًا',
                                90 => '90 يومًا',
                            ])
                            ->default(
                                OverdueCustomerReportService::DEFAULT_CREDIT_DAYS
                            )
                            ->native(false),

                        TextInput::make('custom_credit_days')
                            ->label('مدة مخصصة بالأيام')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365),

                        DatePicker::make('as_of')
                            ->label('كما في تاريخ')
                            ->default(today())
                            ->native(false)
                            ->displayFormat('Y-m-d'),

                        Select::make('risk')
                            ->label('حالة المخاطر')
                            ->options(
                                OverdueCustomerReportService::riskOptions()
                            )
                            ->native(false),

                        TextInput::make('minimum_overdue')
                            ->label('الحد الأدنى للمبلغ المتأخر')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $settings = self::settingsFromFilterData($data);

                        $ids = app(OverdueCustomerReportService::class)
                            ->customerIds(
                                creditDays: $settings['credit_days'],
                                asOf: $settings['as_of'],
                                criteria: [
                                    'scope' => $settings['scope'],
                                    'risk' => $settings['risk'],
                                    'minimum_overdue' => $settings['minimum_overdue'],
                                ],
                            );

                        return $ids === []
                            ? $query->whereRaw('1 = 0')
                            : $query->whereIn('customers.id', $ids);
                    })
                    ->default(),

                SelectFilter::make('area_id')
                    ->label('المنطقة')
                    ->relationship('area', 'name_ar')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('route_id')
                    ->label('خط التوزيع')
                    ->relationship('route', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('payment_type')
                    ->label('نوع الدفع')
                    ->options(
                        OverdueCustomerReportService::paymentTypeOptions()
                    ),

                SelectFilter::make('customer_type')
                    ->label('نوع العميل')
                    ->options([
                        'grocery' => 'بقالية',
                        'supermarket' => 'سوبر ماركت',
                        'restaurant' => 'مطعم',
                        'wholesaler' => 'تاجر جملة',
                        'other' => 'أخرى',
                    ]),

                SelectFilter::make('status')
                    ->label('حالة العميل')
                    ->options([
                        'active' => 'نشط',
                        'inactive' => 'غير نشط',
                    ]),
            ])
            ->recordActions([
                Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
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
                            auth()->user()?->canManageSalesAndCollections()
                                === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->defaultSort('name');
    }

    public static function printUrlFor(
        Customer $record,
        array $settings = [],
    ): string {
        return route('reports.overdue-customers.print', [
            'customer' => $record->getKey(),
            'credit_days' => $settings['credit_days']
                ?? OverdueCustomerReportService::DEFAULT_CREDIT_DAYS,
            'as_of' => $settings['as_of']
                ?? today()->toDateString(),
        ]);
    }

    public static function settingsFromLivewire(mixed $livewire): array
    {
        $filters = is_array($livewire->tableFilters ?? null)
            ? $livewire->tableFilters
            : [];

        $data = is_array($filters['overdue_settings'] ?? null)
            ? $filters['overdue_settings']
            : [];

        return self::settingsFromFilterData($data);
    }

    public static function settingsFromFilterData(array $data): array
    {
        $customDays = is_numeric($data['custom_credit_days'] ?? null)
            ? (int) $data['custom_credit_days']
            : null;

        $selectedDays = is_numeric($data['credit_days'] ?? null)
            ? (int) $data['credit_days']
            : OverdueCustomerReportService::DEFAULT_CREDIT_DAYS;

        $creditDays = $customDays !== null && $customDays > 0
            ? $customDays
            : $selectedDays;

        $creditDays = min(max($creditDays, 1), 365);

        return [
            'scope' => in_array(
                $data['scope'] ?? null,
                ['overdue', 'all_positive'],
                true,
            )
                ? (string) $data['scope']
                : 'overdue',
            'credit_days' => $creditDays,
            'as_of' => self::normalizeDate($data['as_of'] ?? null)
                ?? today()->toDateString(),
            'risk' => in_array(
                $data['risk'] ?? null,
                array_keys(OverdueCustomerReportService::riskOptions()),
                true,
            )
                ? (string) $data['risk']
                : null,
            'minimum_overdue' => max(
                (float) ($data['minimum_overdue'] ?? 0),
                0,
            ),
        ];
    }

    private static function summaryForRecord(
        Customer $record,
        mixed $livewire,
    ): array {
        $settings = self::settingsFromLivewire($livewire);

        return app(OverdueCustomerReportService::class)
            ->summaryForCustomer(
                customerId: (int) $record->getKey(),
                creditDays: $settings['credit_days'],
                asOf: $settings['as_of'],
            );
    }

    private static function summarizeQuery(
        QueryBuilder $query,
        mixed $livewire,
        string $field,
    ): float {
        $settings = self::settingsFromLivewire($livewire);

        $ids = (clone $query)
            ->reorder()
            ->pluck('customers.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return 0.0;
        }

        return (float) app(OverdueCustomerReportService::class)
            ->summaries(
                creditDays: $settings['credit_days'],
                asOf: $settings['as_of'],
            )
            ->only($ids)
            ->sum($field);
    }

    private static function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
