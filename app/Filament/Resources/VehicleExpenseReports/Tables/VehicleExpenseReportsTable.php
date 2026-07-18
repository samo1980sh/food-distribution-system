<?php

namespace App\Filament\Resources\VehicleExpenseReports\Tables;

use App\Enums\PermissionName;
use App\Models\VehicleExpense;
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

class VehicleExpenseReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('expense_number')
                    ->label('رقم المصروف')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('تم نسخ رقم المصروف'),

                TextColumn::make('expense_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable()
                    ->description(
                        fn (VehicleExpense $record): ?string => $record->approved_at
                            ? 'الاعتماد: '.$record->approved_at->format('Y-m-d H:i')
                            : null,
                    )
                    ->summarize(
                        Count::make()
                            ->label('عدد المصاريف')
                    ),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('expense_type')
                    ->label('نوع المصروف')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => self::expenseTypeLabel($state)
                    )
                    ->color(fn (?string $state): string => match ($state) {
                        'fuel' => 'warning',
                        'maintenance' => 'danger',
                        'washing' => 'info',
                        'fees' => 'primary',
                        'parking' => 'gray',
                        'emergency' => 'danger',
                        'other' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => self::paymentMethodLabel($state)
                    )
                    ->color(fn (?string $state): string => match ($state) {
                        'cash' => 'success',
                        'bank_transfer' => 'info',
                        'cheque' => 'warning',
                        'other' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('SYP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->summarize([
                        Sum::make()
                            ->label('إجمالي المصاريف')
                            ->money('SYP'),

                        Summarizer::make()
                            ->label('المصاريف النقدية')
                            ->using(
                                fn (QueryBuilder $query): float =>
                                    (float) (clone $query)
                                        ->where('payment_method', 'cash')
                                        ->sum('amount')
                            )
                            ->money('SYP'),

                        Summarizer::make()
                            ->label('المصاريف غير النقدية')
                            ->using(
                                fn (QueryBuilder $query): float =>
                                    (float) (clone $query)
                                        ->where('payment_method', '!=', 'cash')
                                        ->sum('amount')
                            )
                            ->money('SYP'),
                    ]),

                TextColumn::make('route.name')
                    ->label('خط التوزيع')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('driver.name')
                    ->label('السائق')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('salesRepresentative.name')
                    ->label('المندوب')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approvedBy.name')
                    ->label('المعتمد بواسطة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approved_at')
                    ->label('تاريخ الاعتماد')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('receipt_path')
                    ->label('الإيصال')
                    ->getStateUsing(
                        fn (VehicleExpense $record): string =>
                            filled($record->receipt_path) ? 'مرفق' : 'غير مرفق'
                    )
                    ->badge()
                    ->color(
                        fn (string $state): string =>
                            $state === 'مرفق' ? 'success' : 'gray'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('expense_date')
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
                                    ->whereDate('expense_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query
                                    ->whereDate('expense_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('vehicle_id')
                    ->label('السيارة')
                    ->relationship('vehicle', 'plate_number')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('warehouse_id')
                    ->label('المستودع')
                    ->relationship('warehouse', 'name')
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
                    ->label('المندوب')
                    ->relationship('salesRepresentative', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('expense_type')
                    ->label('نوع المصروف')
                    ->options(self::expenseTypeOptions()),

                SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options(self::paymentMethodOptions()),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersFormSchema(fn (array $filters): array => [
                Section::make('الفترة والتصنيف')
                    ->description('حدد الفترة ونوع المصروف وطريقة الدفع المطلوبة.')
                    ->schema([
                        $filters['expense_date'],
                        $filters['expense_type'],
                        $filters['payment_method'],
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('السيارة ونطاق التشغيل')
                    ->description('ضيّق النتائج حسب السيارة أو المستودع أو خط التوزيع.')
                    ->schema([
                        $filters['vehicle_id'],
                        $filters['warehouse_id'],
                        $filters['route_id'],
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('فريق التوزيع')
                    ->description('حدد السائق أو مندوب المبيعات المرتبط بالمصروف عند الحاجة.')
                    ->schema([
                        $filters['driver_id'],
                        $filters['sales_representative_id'],
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
                    ->modalHeading('خيارات تصفية تقرير مصاريف السيارات')
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
                    ->modalHeading('إدارة أعمدة تقرير مصاريف السيارات')
                    ->modalWidth(Width::ThreeExtraLarge),
            )
            ->columnManagerResetActionPosition(ColumnManagerResetActionPosition::Footer)
            ->recordActions([
                Action::make('print')
                    ->label('طباعة المصروف')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('طباعة المصروف')
                    ->url(
                        fn (VehicleExpense $record): string => self::printUrlFor($record),
                        shouldOpenInNewTab: true,
                    )
                    ->visible(
                        fn (VehicleExpense $record): bool =>
                            $record->isApproved()
                            && auth()->user()?->can(PermissionName::REPORT_VEHICLE_EXPENSES->value) === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->defaultSort('expense_date', 'desc')
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->stackedOnMobile()
            ->emptyStateIcon('heroicon-o-receipt-percent')
            ->emptyStateHeading('لا توجد نتائج في تقرير مصاريف السيارات')
            ->emptyStateDescription('غيّر خيارات التقرير أو أزل عوامل التصفية الحالية لعرض مصاريف أخرى.');
    }

    public static function printUrlFor(VehicleExpense $record): string
    {
        return route('reports.vehicle-expenses.print', [
            'vehicleExpense' => $record->getKey(),
        ]);
    }

    public static function expenseTypeOptions(): array
    {
        return [
            'fuel' => 'وقود',
            'maintenance' => 'صيانة',
            'washing' => 'غسيل',
            'fees' => 'رسوم',
            'parking' => 'موقف',
            'emergency' => 'طارئ',
            'other' => 'أخرى',
        ];
    }

    public static function paymentMethodOptions(): array
    {
        return [
            'cash' => 'نقدي',
            'bank_transfer' => 'تحويل بنكي',
            'cheque' => 'شيك',
            'other' => 'أخرى',
        ];
    }

    public static function expenseTypeLabel(?string $value): string
    {
        return self::expenseTypeOptions()[$value] ?? ($value ?: '-');
    }

    public static function paymentMethodLabel(?string $value): string
    {
        return self::paymentMethodOptions()[$value] ?? ($value ?: '-');
    }
}
