<?php

namespace App\Filament\Resources\VehicleLoadReports\Tables;

use App\Enums\PermissionName;
use App\Models\VehicleLoad;
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

class VehicleLoadReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('load_number')
                    ->label('رقم أمر التحميل')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('تم نسخ رقم أمر التحميل'),

                TextColumn::make('load_date')
                    ->label('تاريخ التحميل')
                    ->date('Y-m-d')
                    ->sortable()
                    ->description(
                        fn (VehicleLoad $record): ?string => $record->approved_at
                            ? 'الاعتماد: '.$record->approved_at->format('Y-m-d H:i')
                            : null,
                    )
                    ->summarize(
                        Count::make()
                            ->label('عدد أوامر التحميل')
                    ),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('route.name')
                    ->label('خط التوزيع')
                    ->searchable()
                    ->placeholder('-')
                    ->wrap(),

                TextColumn::make('fromWarehouse.name')
                    ->label('المستودع المصدر')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('total_quantity')
                    ->label('إجمالي الكمية')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->summarize(
                        Sum::make()
                            ->label('إجمالي الكميات')
                            ->numeric(decimalPlaces: 3)
                    ),

                TextColumn::make('total_cost')
                    ->label('إجمالي التكلفة')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
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

                TextColumn::make('driver.name')
                    ->label('السائق')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('salesRepresentative.name')
                    ->label('مندوب المبيعات')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('toWarehouse.name')
                    ->label('مستودع السيارة')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('items_count')
                    ->label('عدد المواد')
                    ->counts('items')
                    ->numeric()
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approved_at')
                    ->label('تاريخ الاعتماد')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->sortable()
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
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersFormSchema(fn (array $filters): array => [
                Section::make('الفترة والحالة')
                    ->description('حدد فترة التحميل والحالة التشغيلية لأوامر التحميل المطلوبة.')
                    ->schema([
                        $filters['load_date'],
                        $filters['status'],
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('السيارة وفريق التوزيع')
                    ->description('ضيّق النتائج حسب السيارة وخط التوزيع والسائق ومندوب المبيعات.')
                    ->schema([
                        $filters['vehicle_id'],
                        $filters['route_id'],
                        $filters['driver_id'],
                        $filters['sales_representative_id'],
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('المستودعات')
                    ->description('حدد المستودع المصدر أو مستودع السيارة الوجهة عند الحاجة.')
                    ->schema([
                        $filters['from_warehouse_id'],
                        $filters['to_warehouse_id'],
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->filtersTriggerAction(
                fn (Action $action): Action => $action
                    ->button()
                    ->label('خيارات التقرير')
                    ->icon('heroicon-o-funnel')
                    ->color('gray')
                    ->modalHeading('خيارات تصفية تقرير تحميلات السيارات')
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
                    ->modalHeading('إدارة أعمدة تقرير تحميلات السيارات')
                    ->modalWidth(Width::ThreeExtraLarge),
            )
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->recordActions([
                Action::make('print')
                    ->label('طباعة أمر التحميل')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('طباعة أمر التحميل')
                    ->url(
                        fn (VehicleLoad $record): string => route(
                            'reports.vehicle-loads.print',
                            $record,
                        ),
                        shouldOpenInNewTab: true,
                    )
                    ->visible(
                        fn (): bool => auth()->user()?->can(PermissionName::REPORT_VEHICLE_LOADS->value) === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->defaultSort('load_date', 'desc')
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->stackedOnMobile()
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('لا توجد نتائج في تقرير تحميلات السيارات')
            ->emptyStateDescription('غيّر خيارات التقرير أو أزل عوامل التصفية الحالية لعرض أوامر تحميل أخرى.');
    }
}
