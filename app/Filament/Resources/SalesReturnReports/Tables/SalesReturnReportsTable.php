<?php

namespace App\Filament\Resources\SalesReturnReports\Tables;

use App\Enums\PermissionName;
use App\Models\SalesReturn;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ColumnManagerLayout;
use Filament\Tables\Enums\ColumnManagerResetActionPosition;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesReturnReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('return_number')
                    ->label('رقم المرتجع')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('تم نسخ رقم المرتجع'),

                TextColumn::make('return_date')
                    ->label('تاريخ المرتجع')
                    ->date('Y-m-d')
                    ->sortable()
                    ->summarize(
                        Count::make()
                            ->label('عدد المرتجعات')
                    ),

                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make('salesInvoice.invoice_number')
                    ->label('الفاتورة الأصلية')
                    ->searchable()
                    ->placeholder('مرتجع مستقل'),

                TextColumn::make('return_reason')
                    ->label('سبب المرتجع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'expired' => 'منتهي الصلاحية',
                        'damaged' => 'تالف',
                        'customer_refused' => 'رفض العميل',
                        'wrong_item' => 'مادة خاطئة',
                        'other' => 'أخرى',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'expired' => 'danger',
                        'damaged' => 'warning',
                        'customer_refused' => 'gray',
                        'wrong_item' => 'info',
                        'other' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('items_count')
                    ->label('عدد المواد')
                    ->counts('items')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('total_amount')
                    ->label('صافي المرتجع')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->summarize(
                        Sum::make()
                            ->label('إجمالي المرتجعات')
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
                    ->label('مندوب المبيعات')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('subtotal')
                    ->label('مجموع المواد')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Sum::make()
                            ->label('إجمالي المواد')
                            ->money('SYP')
                    ),

                TextColumn::make('discount_amount')
                    ->label('الحسم')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(
                        Sum::make()
                            ->label('إجمالي الحسومات')
                            ->money('SYP')
                    ),

                TextColumn::make('confirmed_at')
                    ->label('تاريخ الاعتماد')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('return_date')
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
                                    ->whereDate('return_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query
                                    ->whereDate('return_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'confirmed' => 'معتمد',
                        'cancelled' => 'ملغي',
                    ]),

                SelectFilter::make('return_reason')
                    ->label('سبب المرتجع')
                    ->options([
                        'expired' => 'منتهي الصلاحية',
                        'damaged' => 'تالف',
                        'customer_refused' => 'رفض العميل',
                        'wrong_item' => 'مادة خاطئة',
                        'other' => 'أخرى',
                    ]),

                SelectFilter::make('customer_id')
                    ->label('العميل')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('sales_invoice_id')
                    ->label('الفاتورة الأصلية')
                    ->relationship('salesInvoice', 'invoice_number')
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
                    ->label('مندوب المبيعات')
                    ->relationship('salesRepresentative', 'name')
                    ->searchable()
                    ->preload(),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersFormSchema(fn (array $filters): array => [
                Section::make('الفترة والتصنيف')
                    ->description('حدد الفترة الزمنية وحالة المرتجع وسببه.')
                    ->schema([
                        $filters['return_date'],
                        $filters['status'],
                        $filters['return_reason'],
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('العميل ونطاق التشغيل')
                    ->description('ضيّق النتائج حسب العميل أو الفاتورة الأصلية أو المستودع أو السيارة أو خط التوزيع أو المندوب.')
                    ->schema([
                        $filters['customer_id'],
                        $filters['sales_invoice_id'],
                        $filters['warehouse_id'],
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
                    ->modalHeading('خيارات تصفية تقرير مرتجعات البيع')
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
                    ->modalHeading('إدارة أعمدة تقرير مرتجعات البيع')
                    ->modalWidth(Width::ThreeExtraLarge),
            )
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->recordActions([
                Action::make('print')
                    ->label('طباعة المرتجع')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('طباعة المرتجع')
                    ->url(
                        fn (SalesReturn $record): string => route(
                            'reports.sales-returns.print',
                            $record,
                        ),
                        shouldOpenInNewTab: true,
                    )
                    ->visible(
                        fn (): bool => auth()->user()?->can(PermissionName::REPORT_SALES_RETURNS->value) === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->defaultSort('return_date', 'desc')
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->stackedOnMobile()
            ->emptyStateIcon('heroicon-o-arrow-path-rounded-square')
            ->emptyStateHeading('لا توجد نتائج في تقرير مرتجعات البيع')
            ->emptyStateDescription('غيّر خيارات التقرير أو أزل عوامل التصفية الحالية لعرض مرتجعات أخرى.');
    }
}
