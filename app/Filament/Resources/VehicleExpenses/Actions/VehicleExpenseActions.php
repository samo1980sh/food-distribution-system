<?php

namespace App\Filament\Resources\VehicleExpenses\Actions;

use App\Models\VehicleExpense;
use App\Services\Distribution\VehicleExpenseService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class VehicleExpenseActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('اعتماد المصروف')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('اعتماد مصروف السيارة')
            ->modalDescription('راجع السيارة والتاريخ والمبلغ والإيصال قبل المتابعة. سيدخل المصروف ضمن الإغلاق اليومي للمستودع المرتبط.')
            ->modalSubmitActionLabel('اعتماد المصروف')
            ->visible(fn (VehicleExpense $record): bool => auth()->user()?->can('approve', $record) === true)
            ->action(function (VehicleExpense $record): void {
                try {
                    Gate::authorize('approve', $record);
                    app(VehicleExpenseService::class)->approve($record);

                    Notification::make()
                        ->title('تم اعتماد المصروف بنجاح')
                        ->body('أصبح المصروف جزءًا من العمليات المالية المعتمدة لليوم.')
                        ->success()
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('تعذر اعتماد المصروف')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('رفض المصروف')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('رفض مصروف السيارة')
            ->modalDescription('اكتب سببًا واضحًا ليظهر في صفحة تفاصيل المصروف وسجل المراجعة.')
            ->modalSubmitActionLabel('تأكيد الرفض')
            ->schema([
                Textarea::make('rejection_reason')
                    ->label('سبب الرفض')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
            ])
            ->visible(fn (VehicleExpense $record): bool => auth()->user()?->can('reject', $record) === true)
            ->action(function (VehicleExpense $record, array $data): void {
                try {
                    Gate::authorize('reject', $record);
                    app(VehicleExpenseService::class)->reject($record, $data['rejection_reason'] ?? null);

                    Notification::make()
                        ->title('تم رفض المصروف')
                        ->body('تم حفظ سبب الرفض وإغلاق المصروف أمام التعديل.')
                        ->success()
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->title('تعذر رفض المصروف')
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
            ->label('طباعة المصروف')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn (VehicleExpense $record): bool => $record->isApproved()
                && auth()->user()?->can('print', $record) === true)
            ->url(fn (VehicleExpense $record): string => route('reports.vehicle-expenses.print', [
                'vehicleExpense' => $record,
            ]))
            ->openUrlInNewTab();
    }

    private function __construct()
    {
    }
}
