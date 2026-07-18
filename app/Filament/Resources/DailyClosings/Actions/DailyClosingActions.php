<?php

namespace App\Filament\Resources\DailyClosings\Actions;

use App\Models\DailyClosing;
use App\Services\Distribution\DailyClosingService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class DailyClosingActions
{
    public static function refreshTotals(): Action
    {
        return Action::make('refreshTotals')
            ->label('تحديث الملخص الدفتري')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('تحديث ملخص الإغلاق')
            ->modalDescription('سيُعاد احتساب حركة المخزون والمبيعات والمرتجعات والتحصيلات والمصاريف مع الاحتفاظ بقيم الجرد الفعلي المدخلة سابقًا.')
            ->modalSubmitActionLabel('تحديث الملخص')
            ->visible(fn (DailyClosing $record): bool => auth()->user()?->can('refreshTotals', $record) === true)
            ->action(function (DailyClosing $record): void {
                try {
                    Gate::authorize('refreshTotals', $record);
                    app(DailyClosingService::class)->refreshTotals($record);
                    $record->refresh();

                    Notification::make()
                        ->title('تم تحديث ملخص الإغلاق')
                        ->body('تمت إعادة بناء الرصيد الدفتري والملخص المالي مع الحفاظ على الجرد الفعلي.')
                        ->success()
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('تعذر تحديث ملخص الإغلاق')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label('اعتماد الإغلاق')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('اعتماد إغلاق اليوم')
            ->modalDescription('يجب إدخال الجرد الفعلي لجميع المواد ومراجعة فرق الصندوق قبل المتابعة. سيُثبت النظام اللقطة ويمنع العمليات اللاحقة على التاريخ والمستودع.')
            ->modalSubmitActionLabel('اعتماد الإغلاق نهائيًا')
            ->visible(fn (DailyClosing $record): bool => auth()->user()?->can('confirm', $record) === true)
            ->action(function (DailyClosing $record): void {
                try {
                    Gate::authorize('confirm', $record);
                    app(DailyClosingService::class)->confirm($record);
                    $record->refresh();

                    Notification::make()
                        ->title('تم اعتماد إغلاق اليوم')
                        ->body('تم تثبيت اللقطة الدفترية والمالية وإقفال التاريخ والمستودع.')
                        ->success()
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('تعذر اعتماد الإغلاق')
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
            ->label('إلغاء الإغلاق')
            ->icon('heroicon-o-lock-open')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('إلغاء إغلاق اليوم')
            ->modalDescription('سيتم تحرير التاريخ والمستودع لإجراء العمليات العكسية أو التصحيحات. تبقى اللقطة السابقة محفوظة كسجل تاريخي.')
            ->modalSubmitActionLabel('تأكيد الإلغاء')
            ->visible(fn (DailyClosing $record): bool => auth()->user()?->can('cancel', $record) === true)
            ->action(function (DailyClosing $record): void {
                try {
                    Gate::authorize('cancel', $record);
                    app(DailyClosingService::class)->cancel($record);
                    $record->refresh();

                    Notification::make()
                        ->title('تم إلغاء الإغلاق')
                        ->body('أصبح التاريخ والمستودع متاحين للتصحيحات والعمليات العكسية.')
                        ->success()
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('تعذر إلغاء الإغلاق')
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
            ->label('طباعة الإغلاق')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn (DailyClosing $record): bool => auth()->user()?->can('print', $record) === true)
            ->url(fn (DailyClosing $record): string => route('reports.daily-closings.print', [
                'dailyClosing' => $record,
            ]))
            ->openUrlInNewTab();
    }

    private function __construct()
    {
    }
}
