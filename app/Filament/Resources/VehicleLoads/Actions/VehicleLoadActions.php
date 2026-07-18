<?php

namespace App\Filament\Resources\VehicleLoads\Actions;

use App\Models\VehicleLoad;
use App\Services\Distribution\VehicleLoadService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class VehicleLoadActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('اعتماد التحميل')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('اعتماد أمر التحميل')
            ->modalDescription('راجع السيارة والمستودعين والمواد قبل المتابعة. سيتم نقل المخزون إلى مستودع السيارة، ولن يبقى الأمر قابلًا للتعديل بعد الاعتماد.')
            ->modalSubmitActionLabel('اعتماد أمر التحميل')
            ->visible(fn (VehicleLoad $record): bool => auth()->user()?->can('approve', $record) === true)
            ->action(function (VehicleLoad $record): void {
                try {
                    Gate::authorize('approve', $record);
                    app(VehicleLoadService::class)->approve($record);

                    Notification::make()
                        ->title('تم اعتماد أمر التحميل بنجاح')
                        ->body('تم نقل الكميات وتثبيت تكلفة المواد داخل مستودع السيارة.')
                        ->success()
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('تعذر اعتماد أمر التحميل')
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
            ->label('إلغاء التحميل')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('إلغاء أمر التحميل')
            ->modalDescription('سيتم عكس حركة المخزون وإرجاع المواد إلى المستودع المصدر. لا يمكن تنفيذ العملية بعد إغلاق يوم المستودع.')
            ->modalSubmitActionLabel('تأكيد الإلغاء')
            ->visible(fn (VehicleLoad $record): bool => auth()->user()?->can('cancel', $record) === true)
            ->action(function (VehicleLoad $record): void {
                try {
                    Gate::authorize('cancel', $record);
                    app(VehicleLoadService::class)->cancel($record);

                    Notification::make()
                        ->title('تم إلغاء أمر التحميل')
                        ->body('تم عكس حركة المخزون وإرجاع الكميات إلى المستودع المصدر.')
                        ->success()
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('تعذر إلغاء أمر التحميل')
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
            ->label('طباعة أمر التحميل')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn (VehicleLoad $record): bool => auth()->user()?->can('print', $record) === true)
            ->url(fn (VehicleLoad $record): string => route('reports.vehicle-loads.print', [
                'vehicleLoad' => $record,
            ]))
            ->openUrlInNewTab();
    }

    private function __construct()
    {
    }
}
