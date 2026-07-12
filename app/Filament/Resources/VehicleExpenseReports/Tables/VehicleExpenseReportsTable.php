<?php

namespace App\Filament\Resources\VehicleExpenseReports\Tables;

use App\Models\VehicleExpense;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
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
                    ->sortable(),

                TextColumn::make('expense_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable()
                    ->summarize(
                        Count::make()
                            ->label('عدد المصاريف')
                    ),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('route.name')
                    ->label('خط التوزيع')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('driver.name')
                    ->label('السائق')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('salesRepresentative.name')
                    ->label('المندوب')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

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

                TextColumn::make('approvedBy.name')
                    ->label('المعتمد بواسطة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('approved_at')
                    ->label('تاريخ الاعتماد')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),

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
            ])
            ->recordActions([
                Action::make('print')
                    ->label('طباعة')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(
                        fn (VehicleExpense $record): string => self::printUrlFor($record),
                        shouldOpenInNewTab: true,
                    )
                    ->visible(
                        fn (VehicleExpense $record): bool =>
                            $record->isApproved()
                            && auth()->user()?->canManageDistribution() === true
                    ),
            ])
            ->toolbarActions([])
            ->summaries(
                pageCondition: false,
                allTableCondition: true,
            )
            ->defaultSort('expense_date', 'desc');
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
