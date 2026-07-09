<?php

namespace App\Filament\Resources\CustomerPayments\Tables;

use App\Models\CustomerPayment;
use App\Models\User;
use App\Services\Sales\CustomerPaymentService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RuntimeException;

class CustomerPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_number')
                    ->label('رقم التحصيل')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('payment_date')
                    ->label('تاريخ التحصيل')
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

                TextColumn::make('salesRepresentative.name')
                    ->label('المندوب')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash' => 'نقدي',
                        'bank_transfer' => 'تحويل بنكي',
                        'cheque' => 'شيك',
                        'other' => 'أخرى',
                        default => $state ?? '-',
                    })
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
                    ->sortable(),

                TextColumn::make('reference_number')
                    ->label('المرجع')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

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

                SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options([
                        'cash' => 'نقدي',
                        'bank_transfer' => 'تحويل بنكي',
                        'cheque' => 'شيك',
                        'other' => 'أخرى',
                    ]),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('اعتماد التحصيل')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('اعتماد التحصيل')
                    ->modalDescription('إذا كان التحصيل مرتبطًا بفاتورة، سيتم تحديث المدفوع والمتبقي على الفاتورة.')
                    ->visible(fn (CustomerPayment $record): bool => $record->isDraft() && self::canConfirmOrEditPayment())
                    ->action(function (CustomerPayment $record): void {
                        try {
                            app(CustomerPaymentService::class)->confirm($record);

                            Notification::make()
                                ->title('تم اعتماد التحصيل بنجاح')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('تعذر اعتماد التحصيل')
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
                    ->modalHeading('إلغاء التحصيل')
                    ->modalDescription('سيتم عكس أثر التحصيل على الفاتورة المرتبطة إن وجدت.')
                    ->visible(fn (CustomerPayment $record): bool => $record->isConfirmed() && self::canCancelPayment())
                    ->action(function (CustomerPayment $record): void {
                        try {
                            app(CustomerPaymentService::class)->cancel($record);

                            Notification::make()
                                ->title('تم إلغاء التحصيل بنجاح')
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('تعذر إلغاء التحصيل')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->label('تعديل')
                    ->modalHeading('تعديل تحصيل عميل')
                    ->slideOver()
                    ->visible(fn (CustomerPayment $record): bool => $record->isDraft() && self::canConfirmOrEditPayment()),

                DeleteAction::make()
                    ->label('حذف')
                    ->visible(fn (CustomerPayment $record): bool => $record->isDraft() && self::canDeleteDraftPayment()),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    private static function canConfirmOrEditPayment(): bool
    {
        return auth()->user()?->canManageSalesAndCollections() === true;
    }

    private static function canCancelPayment(): bool
    {
        return auth()->user()?->hasAnyRole([
            User::ROLE_SUPER_ADMIN,
            User::ROLE_MANAGER,
            User::ROLE_ACCOUNTANT,
        ]) === true;
    }

    private static function canDeleteDraftPayment(): bool
    {
        return auth()->user()?->hasAnyRole([
            User::ROLE_SUPER_ADMIN,
            User::ROLE_MANAGER,
        ]) === true;
    }
}