<?php

namespace App\Filament\Resources\VehicleStockReports\Tables;

use App\Enums\PermissionName;
use App\Models\StockBalance;
use App\Models\Vehicle;
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
                    ->sortable()
                    ->weight('bold')
                    ->description(
                        fn (StockBalance $record): ?string => filled($record->warehouse?->name)
                            ? 'المستودع: '.$record->warehouse->name
                            : null,
                    ),

                TextColumn::make('product.name_ar')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap()
                    ->description(
                        fn (StockBalance $record): ?string => filled($record->product?->sku)
                            ? 'SKU: '.$record->product->sku
                            : null,
                    ),

                TextColumn::make('batch_number')
                    ->label('رقم التشغيلة')
                    ->searchable()
                    ->placeholder('-'),

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
                    ->alignEnd()
                    ->weight('bold')
                    ->summarize([
                        Count::make()
                            ->label('عدد الأرصدة'),

                        Sum::make()
                            ->label('إجمالي الكمية')
                            ->numeric(decimalPlaces: 3),
                    ]),

                TextColumn::make('inventory_value')
                    ->label('قيمة المخزون')
                    ->getStateUsing(
                        fn (StockBalance $record): float =>
                            (float) $record->quantity
                            * (float) $record->average_unit_cost
                    )
                    ->money('SYP')
                    ->alignEnd()
                    ->weight('bold'),

                TextColumn::make('warehouse.name')
                    ->label('مستودع السيارة')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('average_unit_cost')
                    ->label('متوسط تكلفة الوحدة')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

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
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersFormSchema(fn (array $filters): array => [
                Section::make('السيارة والمنتج')
                    ->description('حدد السيارة أو المنتج للوصول إلى الرصيد الحالي المطلوب بسرعة.')
                    ->schema([
                        $filters['vehicle_id'],
                        $filters['product_id'],
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('الصلاحية')
                    ->description('اعرض الأرصدة ضمن فترة صلاحية محددة أو حسب حالة انتهاء المنتج.')
                    ->schema([
                        $filters['expiry_date'],
                        $filters['expiry_status'],
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
                    ->modalHeading('خيارات تصفية تقرير مخزون السيارات')
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
                    ->modalHeading('إدارة أعمدة تقرير مخزون السيارات')
                    ->modalWidth(Width::ThreeExtraLarge),
            )
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->recordActions([
                Action::make('printVehicleStock')
                    ->label('طباعة مخزون السيارة')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('طباعة مخزون السيارة')
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
            ->defaultSort('updated_at', 'desc')
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->stackedOnMobile()
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading('لا توجد أرصدة في تقرير مخزون السيارات')
            ->emptyStateDescription('غيّر خيارات التقرير أو أزل عوامل التصفية الحالية لعرض أرصدة أخرى.');
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
