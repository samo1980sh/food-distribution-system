<?php

namespace App\Filament\Resources\ProfitReports\Tables;

use App\Enums\PermissionName;
use App\Models\ProfitReportEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ColumnManagerLayout;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\FiltersResetActionPosition;
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
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('تم نسخ رقم المستند'),

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
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make('sales_amount')
                    ->label('صافي المبيعات')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->summarize(
                        Sum::make()
                            ->label('صافي المبيعات')
                            ->money('SYP')
                    ),

                TextColumn::make('cost_amount')
                    ->label('تكلفة البضاعة')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->label('صافي التكلفة')
                            ->money('SYP')
                    ),

                TextColumn::make('profit_amount')
                    ->label('مجمل الربح')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
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
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn ($state): string => match (true) {
                        (float) $state > 0 => 'success',
                        (float) $state < 0 => 'danger',
                        default => 'gray',
                    })
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

                TextColumn::make('quantity')
                    ->label('صافي الكمية')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->label('صافي الكمية')
                            ->numeric(decimalPlaces: 3)
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('route.name')
                    ->label('خط التوزيع')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('salesRepresentative.name')
                    ->label('المندوب')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersFormSchema(fn (array $filters): array => [
                Section::make('الفترة ونوع الحركة')
                    ->description('حدد الفترة المالية ونوع الحركة الداخلة في قراءة الربحية.')
                    ->schema([
                        $filters['entry_date'],
                        $filters['entry_type'],
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('العميل والمستودع')
                    ->description('ضيّق النتائج حسب العميل أو المستودع المرتبط بالحركة.')
                    ->schema([
                        $filters['customer_id'],
                        $filters['warehouse_id'],
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('نطاق التوزيع')
                    ->description('حلّل الربحية حسب السيارة أو خط التوزيع أو مندوب المبيعات.')
                    ->schema([
                        $filters['vehicle_id'],
                        $filters['route_id'],
                        $filters['sales_representative_id'],
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ])
            ->filtersTriggerAction(
                fn (Action $action): Action => $action
                    ->button()
                    ->label('خيارات التقرير')
                    ->icon('heroicon-o-funnel')
                    ->color('gray')
                    ->modalHeading('خيارات تصفية تقرير الأرباح التقريبية')
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
                    ->modalHeading('إدارة أعمدة تقرير الأرباح التقريبية')
                    ->modalWidth(Width::ThreeExtraLarge),
            )
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->recordActions([
                Action::make('print')
                    ->label('طباعة المستند')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('طباعة المستند')
                    ->url(
                        fn (ProfitReportEntry $record): ?string => self::printUrlFor($record),
                        shouldOpenInNewTab: true,
                    )
                    ->visible(
                        fn (ProfitReportEntry $record): bool =>
                            in_array($record->entry_type, ['invoice', 'return'], true)
                            && auth()->user()?->can(PermissionName::REPORT_PROFIT->value) === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->defaultSort('entry_date', 'desc')
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->stackedOnMobile()
            ->emptyStateIcon('heroicon-o-chart-bar-square')
            ->emptyStateHeading('لا توجد نتائج في تقرير الأرباح التقريبية')
            ->emptyStateDescription('غيّر خيارات التقرير أو أزل عوامل التصفية الحالية لعرض حركات ربحية أخرى.');
    }

    public static function printUrlFor(ProfitReportEntry $record): ?string
    {
        return match ($record->entry_type) {
            'invoice' => route('reports.sales-invoices.print', [
                'salesInvoice' => $record->source_id,
            ]),
            'return' => route('reports.sales-returns.print', [
                'salesReturn' => $record->source_id,
            ]),
            default => null,
        };
    }
}
