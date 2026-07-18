<?php

namespace App\Filament\Resources\SalesInvoices\Actions;

use App\Models\SalesInvoice;
use App\Services\Sales\SalesInvoiceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class SalesInvoiceActions
{
    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label('اعتماد الفاتورة')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('اعتماد فاتورة البيع')
            ->modalDescription('راجع العميل والمواد والإجمالي قبل المتابعة. سيُخصم المخزون، ولن تبقى الفاتورة قابلة للتعديل بعد الاعتماد.')
            ->modalSubmitActionLabel('اعتماد الفاتورة')
            ->visible(fn (SalesInvoice $record): bool => auth()->user()?->can('confirm', $record) === true)
            ->action(function (SalesInvoice $record): void {
                try {
                    Gate::authorize('confirm', $record);
                    app(SalesInvoiceService::class)->confirm($record);

                    Notification::make()
                        ->title('تم اعتماد الفاتورة بنجاح')
                        ->body('تم تحديث المخزون والأرصدة المالية المرتبطة بالفاتورة.')
                        ->success()
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('تعذر اعتماد الفاتورة')
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
            ->label('إلغاء الفاتورة')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('إلغاء فاتورة البيع')
            ->modalDescription('سيتم عكس حركة المخزون. يجب إلغاء أي تحصيلات مرتبطة أولًا، ولا يمكن التراجع عن هذه العملية من الواجهة.')
            ->modalSubmitActionLabel('تأكيد الإلغاء')
            ->visible(fn (SalesInvoice $record): bool => auth()->user()?->can('cancel', $record) === true)
            ->action(function (SalesInvoice $record): void {
                try {
                    Gate::authorize('cancel', $record);
                    app(SalesInvoiceService::class)->cancel($record);

                    Notification::make()
                        ->title('تم إلغاء الفاتورة')
                        ->body('تم عكس حركة المخزون وتحديث حالة الفاتورة.')
                        ->success()
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('تعذر إلغاء الفاتورة')
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
            ->label('طباعة الفاتورة')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->url(fn (SalesInvoice $record): string => route('reports.sales-invoices.print', [
                'salesInvoice' => $record,
            ]))
            ->openUrlInNewTab();
    }

    private function __construct()
    {
    }
}
