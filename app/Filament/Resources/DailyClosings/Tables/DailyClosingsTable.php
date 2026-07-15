<?php

namespace App\Filament\Resources\DailyClosings\Tables;

use App\Models\DailyClosing;
use App\Services\Distribution\DailyClosingService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class DailyClosingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('closing_number')
                    ->label('رقم الإغلاق')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('closing_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->searchable(),

                TextColumn::make('salesRepresentative.name')
                    ->label('المندوب')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('total_sales_amount')
                    ->label('المبيعات')
                    ->money('SYP')
                    ->sortable(),

                TextColumn::make('total_returns_amount')
                    ->label('المرتجعات')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('net_sales_amount')
                    ->label('صافي المبيعات')
                    ->state(fn (DailyClosing $record): float => max(
                        (float) $record->total_sales_amount - (float) $record->total_returns_amount,
                        0,
                    ))
                    ->money('SYP')
                    ->toggleable(),

                TextColumn::make('total_collections_amount')
                    ->label('إجمالي التحصيلات')
                    ->money('SYP')
                    ->sortable(),

                TextColumn::make('invoice_cash_amount')
                    ->label('نقد الفواتير')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('cash_collections_amount')
                    ->label('تحصيل نقدي')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('non_cash_collections_amount')
                    ->label('تحصيل غير نقدي')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_vehicle_expenses_amount')
                    ->label("\u{0645}\u{0635}\u{0627}\u{0631}\u{064A}\u{0641} \u{0627}\u{0644}\u{0633}\u{064A}\u{0627}\u{0631}\u{0627}\u{062A}")
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('cash_vehicle_expenses_amount')
                    ->label("\u{0645}\u{0635}\u{0627}\u{0631}\u{064A}\u{0641} \u{0646}\u{0642}\u{062F}\u{064A}\u{0629}")
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('non_cash_vehicle_expenses_amount')
                    ->label("\u{0645}\u{0635}\u{0627}\u{0631}\u{064A}\u{0641} \u{063A}\u{064A}\u{0631} \u{0646}\u{0642}\u{062F}\u{064A}\u{0629}")
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('expected_cash_amount')
                    ->label('النقد المتوقع')
                    ->money('SYP')
                    ->sortable(),

                TextColumn::make('actual_cash_amount')
                    ->label('النقد الفعلي')
                    ->money('SYP')
                    ->sortable(),

                TextColumn::make('cash_difference')
                    ->label('فرق الصندوق')
                    ->money('SYP')
                    ->sortable()
                    ->color(fn ($state): string => ((float) $state) === 0.0 ? 'success' : 'warning'),

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
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'confirmed' => 'معتمد',
                        'cancelled' => 'ملغي',
                    ]),

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

                SelectFilter::make('sales_representative_id')
                    ->label('المندوب')
                    ->relationship('salesRepresentative', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('refreshTotals')
                    ->label('تحديث الملخص')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (DailyClosing $record): bool => auth()->user()?->can('refreshTotals', $record) === true)
                    ->action(function (DailyClosing $record): void {
                        Gate::authorize('refreshTotals', $record);
                        app(DailyClosingService::class)->refreshTotals($record);

                        Notification::make()
                            ->title('تم تحديث ملخص الإغلاق')
                            ->success()
                            ->send();
                    }),

                Action::make('confirm')
                    ->label('اعتماد الإغلاق')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('اعتماد إغلاق اليوم')
                    ->modalDescription('سيتم اعتماد الإغلاق ومنع تعديل عملياته لاحقاً.')
                    ->visible(fn (DailyClosing $record): bool => auth()->user()?->can('confirm', $record) === true)
                    ->action(function (DailyClosing $record): void {
                        try {
                            Gate::authorize('confirm', $record);
                            app(DailyClosingService::class)->confirm($record);

                            Notification::make()
                                ->title('تم اعتماد إغلاق اليوم بنجاح')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('تعذر اعتماد الإغلاق')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('cancel')
                    ->label('إلغاء الإغلاق')
                    ->icon('heroicon-o-lock-open')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء إغلاق اليوم')
                    ->modalDescription('سيتم فتح التاريخ والمستودع للعمليات العكسية والتصحيحات.')
                    ->visible(fn (DailyClosing $record): bool => auth()->user()?->can('cancel', $record) === true)
                    ->action(function (DailyClosing $record): void {
                        try {
                            Gate::authorize('cancel', $record);
                            app(DailyClosingService::class)->cancel($record);

                            Notification::make()
                                ->title('تم إلغاء الإغلاق بنجاح')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('تعذر إلغاء الإغلاق')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->label('تعديل')
                    ->modalHeading('تعديل إغلاق يوم')
                    ->slideOver()
                    ->visible(fn (DailyClosing $record): bool => auth()->user()?->can('update', $record) === true),

                DeleteAction::make()
                    ->label('حذف')
                    ->visible(fn (DailyClosing $record): bool => auth()->user()?->can('delete', $record) === true),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}