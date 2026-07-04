<?php

namespace App\Filament\Resources\DailyClosings\Tables;

use App\Models\DailyClosing;
use App\Services\Distribution\DailyClosingService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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

                TextColumn::make('total_collections_amount')
                    ->label('التحصيلات')
                    ->money('SYP')
                    ->sortable(),

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
                    ->visible(fn (DailyClosing $record): bool => $record->isDraft())
                    ->action(function (DailyClosing $record): void {
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
                    ->modalDescription('سيتم اعتماد الإغلاق ومنع تعديله لاحقًا.')
                    ->visible(fn (DailyClosing $record): bool => $record->isDraft())
                    ->action(function (DailyClosing $record): void {
                        try {
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

                EditAction::make()
                    ->label('تعديل')
                    ->modalHeading('تعديل إغلاق يوم')
                    ->slideOver()
                    ->visible(fn (DailyClosing $record): bool => $record->isDraft()),

                DeleteAction::make()
                    ->label('حذف')
                    ->visible(fn (DailyClosing $record): bool => $record->isDraft()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('حذف المحدد'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}