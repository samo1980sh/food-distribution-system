<?php

namespace App\Filament\Resources\SalesReturns\Actions;

use App\Models\SalesReturn;
use App\Services\Sales\SalesReturnService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class SalesReturnActions
{
    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label('اعتماد المرتجع')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('اعتماد مرتجع البيع')
            ->modalDescription('راجع الفاتورة الأصلية والمواد والمستودع قبل المتابعة. ستُضاف الكميات إلى المخزون، ولن يبقى المرتجع قابلاً للتعديل.')
            ->modalSubmitActionLabel('اعتماد المرتجع')
            ->visible(fn (SalesReturn $record): bool => auth()->user()?->can('confirm', $record) === true)
            ->action(function (SalesReturn $record): void {
                try {
                    Gate::authorize('confirm', $record);
                    app(SalesReturnService::class)->confirm($record);

                    Notification::make()
                        ->title('تم اعتماد المرتجع بنجاح')
                        ->body('تم تحديث المخزون والأثر المالي للفاتورة الأصلية إن وجدت.')
                        ->success()
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('تعذر اعتماد المرتجع')
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
            ->label('إلغاء المرتجع')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('إلغاء مرتجع البيع')
            ->modalDescription('سيتم عكس حركة المخزون وإخراج الكميات التي أُعيدت عند الاعتماد، مع تحديث الأثر المالي المرتبط.')
            ->modalSubmitActionLabel('تأكيد الإلغاء')
            ->visible(fn (SalesReturn $record): bool => auth()->user()?->can('cancel', $record) === true)
            ->action(function (SalesReturn $record): void {
                try {
                    Gate::authorize('cancel', $record);
                    app(SalesReturnService::class)->cancel($record);

                    Notification::make()
                        ->title('تم إلغاء المرتجع')
                        ->body('تم عكس حركة المخزون وتحديث حالة المرتجع.')
                        ->success()
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('تعذر إلغاء المرتجع')
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
            ->label('طباعة المرتجع')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn (SalesReturn $record): bool => auth()->user()?->can('print', $record) === true)
            ->url(fn (SalesReturn $record): string => route('reports.sales-returns.print', [
                'salesReturn' => $record,
            ]))
            ->openUrlInNewTab();
    }

    private function __construct()
    {
    }
}
