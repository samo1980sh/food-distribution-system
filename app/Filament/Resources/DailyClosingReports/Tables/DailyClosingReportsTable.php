<?php

namespace App\Filament\Resources\DailyClosingReports\Tables;

use App\Enums\PermissionName;
use App\Models\DailyClosing;
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

class DailyClosingReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('closing_number')
                    ->label('الإغلاق')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('تم نسخ رقم الإغلاق')
                    ->description(
                        fn (DailyClosing $record): string => 'التاريخ: '.$record->closing_date->format('Y-m-d'),
                    )
                    ->summarize(
                        Count::make()
                            ->label('عدد الإغلاقات')
                    ),

                TextColumn::make('warehouse.name')
                    ->label('نطاق الإغلاق')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap()
                    ->description(
                        fn (DailyClosing $record): ?string => $record->vehicle?->plate_number
                            ? 'السيارة: '.$record->vehicle->plate_number
                            : null,
                    ),

                TextColumn::make('net_sales_amount')
                    ->label('صافي المبيعات')
                    ->state(fn (DailyClosing $record): float => max(
                        (float) $record->total_sales_amount
                        - (float) $record->total_returns_amount,
                        0,
                    ))
                    ->money('SYP')
                    ->alignEnd()
                    ->weight('bold')
                    ->summarize(
                        Summarizer::make()
                            ->label('الإجمالي')
                            ->using(fn (QueryBuilder $query): float => max(
                                (float) $query->sum('total_sales_amount')
                                - (float) $query->sum('total_returns_amount'),
                                0,
                            ))
                            ->money('SYP')
                    ),

                TextColumn::make('total_collections_amount')
                    ->label('التحصيلات')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('total_vehicle_expenses_amount')
                    ->label('مصاريف السيارات')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('expected_cash_amount')
                    ->label('النقد المتوقع')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('actual_cash_amount')
                    ->label('النقد الفعلي')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('cash_difference')
                    ->label('فرق الصندوق')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn ($state): string => ((float) $state) === 0.0 ? 'success' : 'warning')
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
                        'confirmed' => 'معتمد',
                        'cancelled' => 'ملغي',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'draft' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('closing_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
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

                TextColumn::make('total_opening_quantity')
                    ->label('رصيد البداية')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(Sum::make()->label('الإجمالي')->numeric(decimalPlaces: 3)),

                TextColumn::make('total_movement_in_quantity')
                    ->label('الوارد الدفتري')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(Sum::make()->label('الإجمالي')->numeric(decimalPlaces: 3)),

                TextColumn::make('total_movement_out_quantity')
                    ->label('الصادر الدفتري')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(Sum::make()->label('الإجمالي')->numeric(decimalPlaces: 3)),

                TextColumn::make('total_expected_quantity')
                    ->label('الرصيد المتوقع')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(Sum::make()->label('الإجمالي')->numeric(decimalPlaces: 3)),

                TextColumn::make('total_loaded_quantity')
                    ->label('الكمية المحمّلة')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->numeric(decimalPlaces: 3)
                    ),

                TextColumn::make('total_sold_quantity')
                    ->label('الكمية المباعة')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->numeric(decimalPlaces: 3)
                    ),

                TextColumn::make('total_returned_quantity')
                    ->label('الكمية المرتجعة')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->numeric(decimalPlaces: 3)
                    ),

                TextColumn::make('total_sales_amount')
                    ->label('المبيعات')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('total_returns_amount')
                    ->label('المرتجعات')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('invoice_cash_amount')
                    ->label('نقد الفواتير')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('cash_collections_amount')
                    ->label('تحصيل نقدي')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),

                TextColumn::make('non_cash_collections_amount')
                    ->label('تحصيل غير نقدي')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Sum::make()
                            ->label('الإجمالي')
                            ->money('SYP')
                    ),
            ])
            ->filters([
                Filter::make('closing_date')
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
                                    ->whereDate('closing_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query
                                    ->whereDate('closing_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'confirmed' => 'معتمد',
                        'cancelled' => 'ملغي',
                    ]),

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
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->filtersFormSchema(fn (array $filters): array => [
                Section::make('خيارات تصفية الإغلاقات')
                    ->description('حدد الفترة والحالة ونطاق المستودع أو السيارة أو خط التوزيع أو المندوب، ثم اعرض النتائج.')
                    ->schema([
                        $filters['closing_date'],
                        $filters['status'],
                        $filters['warehouse_id'],
                        $filters['vehicle_id'],
                        $filters['route_id'],
                        $filters['sales_representative_id'],
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ])
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
                    ->modalHeading('إدارة أعمدة تقرير الإغلاق اليومي')
                    ->modalWidth(Width::FiveExtraLarge),
            )
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->recordActions([
                Action::make('print')
                    ->label('طباعة الإغلاق')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('طباعة الإغلاق اليومي')
                    ->url(
                        fn (DailyClosing $record): string => route(
                            'reports.daily-closings.print',
                            $record,
                        ),
                        shouldOpenInNewTab: true,
                    )
                    ->visible(
                        fn (): bool => auth()->user()?->can(PermissionName::REPORT_DAILY_CLOSINGS->value) === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->defaultSort('closing_date', 'desc')
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->stackedOnMobile()
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->emptyStateHeading('لا توجد إغلاقات يومية ضمن النطاق المحدد')
            ->emptyStateDescription('غيّر خيارات التصفية أو أزلها لعرض إغلاقات يومية أخرى.');
    }
}
