<?php

namespace App\Filament\Resources\CustomerPayments\Actions;

use App\Models\CustomerPayment;
use App\Services\Sales\CustomerPaymentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class CustomerPaymentActions
{
    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label('اعتماد التحصيل')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('اعتماد تحصيل العميل')
            ->modalDescription('راجع العميل والمبلغ وطريقة الدفع والفاتورة المرتبطة. سيُحدّث رصيد الفاتورة والإغلاق اليومي بعد الاعتماد.')
            ->modalSubmitActionLabel('اعتماد التحصيل')
            ->visible(fn (CustomerPayment $record): bool => auth()->user()?->can('confirm', $record) === true)
            ->action(function (CustomerPayment $record): void {
                try {
                    Gate::authorize('confirm', $record);
                    app(CustomerPaymentService::class)->confirm($record);

                    Notification::make()
                        ->title('تم اعتماد التحصيل بنجاح')
                        ->body('تم تحديث الرصيد المالي للفاتورة المرتبطة إن وجدت.')
                        ->success()
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('تعذر اعتماد التحصيل')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('إلغاء التحصيل')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('إلغاء تحصيل العميل')
            ->modalDescription('سيتم عكس أثر التحصيل على الفاتورة المرتبطة، ولن يُحتسب المبلغ ضمن التحصيلات الفعالة.')
            ->modalSubmitActionLabel('تأكيد الإلغاء')
            ->visible(fn (CustomerPayment $record): bool => auth()->user()?->can('cancel', $record) === true)
            ->action(function (CustomerPayment $record): void {
                try {
                    Gate::authorize('cancel', $record);
                    app(CustomerPaymentService::class)->cancel($record);

                    Notification::make()
                        ->title('تم إلغاء التحصيل')
                        ->body('تم عكس الأثر المالي وتحديث حالة التحصيل.')
                        ->success()
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('تعذر إلغاء التحصيل')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public static function print(): Action
    {
        return Action::make('print')
            ->label('طباعة سند التحصيل')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn (CustomerPayment $record): bool => auth()->user()?->can('print', $record) === true)
            ->url(fn (CustomerPayment $record): string => route('reports.customer-payments.print', [
                'customerPayment' => $record,
            ]))
            ->openUrlInNewTab();
    }

    private function __construct()
    {
    }
}
