<?php

namespace App\Filament\Resources\SalesReturns\Tables;

use App\Models\SalesReturn;
use App\Services\Sales\SalesReturnService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RuntimeException;

class SalesReturnsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('return_number')
                    ->label('رقم المرتجع')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('return_date')
                    ->label('تاريخ المرتجع')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('salesInvoice.invoice_number')
                    ->label('الفاتورة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('warehouse.name')
                    ->label('المستودع')
                    ->searchable(),

                TextColumn::make('return_reason')
                    ->label('السبب')
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

                TextColumn::make('subtotal')
                    ->label('المجموع')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_amount')
                    ->label('الإجمالي')
                    ->money('SYP')
                    ->sortable(),

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

                TextColumn::make('confirmed_at')
                    ->label('تاريخ الاعتماد')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'confirmed' => 'معتمد',
                        'cancelled' => 'ملغي',
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

                SelectFilter::make('return_reason')
                    ->label('سبب المرتجع')
                    ->options([
                        'expired' => 'منتهي الصلاحية',
                        'damaged' => 'تالف',
                        'customer_refused' => 'رفض العميل',
                        'wrong_item' => 'مادة خاطئة',
                        'other' => 'أخرى',
                    ]),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('اعتماد المرتجع')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('اعتماد مرتجع البيع')
                    ->modalDescription('سيتم إضافة الكميات المرتجعة إلى المستودع المحدد، ولا يمكن تعديل المرتجع بعد الاعتماد.')
                    ->visible(fn (SalesReturn $record): bool => $record->isDraft())
                    ->action(function (SalesReturn $record): void {
                        try {
                            app(SalesReturnService::class)->confirm($record);

                            Notification::make()
                                ->title('تم اعتماد المرتجع بنجاح')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('تعذر اعتماد المرتجع')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('cancel')
                    ->label('إلغاء')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء مرتجع البيع')
                    ->modalDescription('سيتم عكس حركة المخزون وإخراج الكميات المرتجعة من المستودع.')
                    ->visible(fn (SalesReturn $record): bool => $record->isConfirmed())
                    ->action(function (SalesReturn $record): void {
                        try {
                            app(SalesReturnService::class)->cancel($record);

                            Notification::make()
                                ->title('تم إلغاء المرتجع بنجاح')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('تعذر إلغاء المرتجع')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->label('تعديل')
                    ->modalHeading('تعديل مرتجع بيع')
                    ->slideOver()
                    ->visible(fn (SalesReturn $record): bool => $record->isDraft()),

                DeleteAction::make()
                    ->label('حذف')
                    ->visible(fn (SalesReturn $record): bool => $record->isDraft()),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}
