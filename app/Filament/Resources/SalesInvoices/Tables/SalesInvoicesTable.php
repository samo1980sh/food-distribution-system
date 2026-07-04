<?php

namespace App\Filament\Resources\SalesInvoices\Tables;

use App\Models\SalesInvoice;
use App\Services\Sales\SalesInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RuntimeException;

class SalesInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('رقم الفاتورة')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice_date')
                    ->label('تاريخ الفاتورة')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vehicle.plate_number')
                    ->label('السيارة')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('warehouse.name')
                    ->label('مستودع البيع')
                    ->searchable(),

                TextColumn::make('salesRepresentative.name')
                    ->label('المندوب')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('payment_type')
                    ->label('الدفع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash' => 'نقدي',
                        'credit' => 'آجل',
                        'partial' => 'جزئي',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'cash' => 'success',
                        'credit' => 'warning',
                        'partial' => 'info',
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

                TextColumn::make('paid_amount')
                    ->label('المدفوع')
                    ->money('SYP')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('remaining_amount')
                    ->label('المتبقي')
                    ->money('SYP')
                    ->sortable()
                    ->color(fn ($state): string => ((float) $state) > 0 ? 'warning' : 'success'),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'draft' => 'مسودة',
                        'confirmed' => 'معتمدة',
                        'cancelled' => 'ملغاة',
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
                        'confirmed' => 'معتمدة',
                        'cancelled' => 'ملغاة',
                    ]),

                SelectFilter::make('customer_id')
                    ->label('العميل')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

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
                Action::make('confirm')
                    ->label('اعتماد الفاتورة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('اعتماد فاتورة البيع')
                    ->modalDescription('سيتم خصم الكميات من مستودع البيع، ولا يمكن تعديل الفاتورة بعد الاعتماد.')
                    ->visible(fn (SalesInvoice $record): bool => $record->isDraft())
                    ->action(function (SalesInvoice $record): void {
                        try {
                            app(SalesInvoiceService::class)->confirm($record);

                            Notification::make()
                                ->title('تم اعتماد الفاتورة بنجاح')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('تعذر اعتماد الفاتورة')
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
                    ->modalHeading('إلغاء فاتورة البيع')
                    ->modalDescription('سيتم عكس حركة المخزون. إذا كانت هناك تحصيلات مرتبطة يجب إلغاؤها أولاً.')
                    ->visible(fn (SalesInvoice $record): bool => $record->isConfirmed())
                    ->action(function (SalesInvoice $record): void {
                        try {
                            app(SalesInvoiceService::class)->cancel($record);

                            Notification::make()
                                ->title('تم إلغاء الفاتورة بنجاح')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('تعذر إلغاء الفاتورة')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->label('تعديل')
                    ->modalHeading('تعديل فاتورة بيع')
                    ->slideOver()
                    ->visible(fn (SalesInvoice $record): bool => $record->isDraft()),

                DeleteAction::make()
                    ->label('حذف')
                    ->visible(fn (SalesInvoice $record): bool => $record->isDraft()),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}
