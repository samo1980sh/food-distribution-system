<?php

namespace App\Filament\Resources\VehicleExpenses\Tables;

use App\Models\VehicleExpense;
use App\Services\Distribution\VehicleExpenseService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RuntimeException;

class VehicleExpensesTable
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
                    ->sortable(),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->searchable()
                    ->toggleable(),

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
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'fuel' => 'وقود',
                        'maintenance' => 'صيانة',
                        'washing' => 'غسيل',
                        'fees' => 'رسوم',
                        'parking' => 'موقف',
                        'emergency' => 'طارئ',
                        'other' => 'أخرى',
                        default => $state ?? '-',
                    }),

                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('SYP')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('الدفع')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash' => 'نقدي',
                        'bank_transfer' => 'تحويل',
                        'cheque' => 'شيك',
                        'other' => 'أخرى',
                        default => $state ?? '-',
                    })
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => 'قيد المراجعة',
                        'approved' => 'معتمد',
                        'rejected' => 'مرفوض',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('approved_at')
                    ->label('تاريخ الاعتماد')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد المراجعة',
                        'approved' => 'معتمد',
                        'rejected' => 'مرفوض',
                    ]),

                SelectFilter::make('expense_type')
                    ->label('نوع المصروف')
                    ->options([
                        'fuel' => 'وقود',
                        'maintenance' => 'صيانة',
                        'washing' => 'غسيل',
                        'fees' => 'رسوم',
                        'parking' => 'موقف',
                        'emergency' => 'طارئ',
                        'other' => 'أخرى',
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
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('اعتماد')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('اعتماد مصروف السيارة')
                    ->modalDescription('بعد الاعتماد سيدخل هذا المصروف لاحقاً ضمن إغلاق اليوم.')
                    ->visible(fn (VehicleExpense $record): bool => $record->isPending())
                    ->action(function (VehicleExpense $record): void {
                        try {
                            app(VehicleExpenseService::class)->approve($record);

                            Notification::make()
                                ->title('تم اعتماد المصروف بنجاح')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('تعذر اعتماد المصروف')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('رفض مصروف السيارة')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('سبب الرفض')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (VehicleExpense $record): bool => $record->isPending())
                    ->action(function (VehicleExpense $record, array $data): void {
                        try {
                            app(VehicleExpenseService::class)->reject($record, $data['rejection_reason'] ?? null);

                            Notification::make()
                                ->title('تم رفض المصروف')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('تعذر رفض المصروف')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->label('تعديل')
                    ->modalHeading('تعديل مصروف سيارة')
                    ->slideOver()
                    ->visible(fn (VehicleExpense $record): bool => $record->isPending()),

                DeleteAction::make()
                    ->label('حذف')
                    ->visible(fn (VehicleExpense $record): bool => $record->isPending()),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}