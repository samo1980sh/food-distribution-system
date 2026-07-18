<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class UserActions
{
    public static function activate(): Action
    {
        return Action::make('activate')
            ->label('تفعيل الحساب')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('تفعيل حساب المستخدم')
            ->modalDescription('سيتمكن المستخدم من تسجيل الدخول واستخدام القنوات المسموح بها وفق أدواره وصلاحياته ونطاقه.')
            ->modalSubmitActionLabel('تفعيل الحساب')
            ->visible(
                fn (User $record): bool => $record->status === User::STATUS_INACTIVE
                    && auth()->user()?->can('update', $record) === true,
            )
            ->action(function (User $record): void {
                self::changeStatus($record, User::STATUS_ACTIVE);
            });
    }

    public static function deactivate(): Action
    {
        return Action::make('deactivate')
            ->label('تعطيل الحساب')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('تعطيل حساب المستخدم')
            ->modalDescription('سيُمنع تسجيل الدخول، وتُلغى جلسات تطبيق الجوال، مع إبقاء السجل والأدوار ونطاقات الوصول محفوظة.')
            ->modalSubmitActionLabel('تعطيل الحساب')
            ->visible(
                fn (User $record): bool => $record->status === User::STATUS_ACTIVE
                    && $record->is(auth()->user()) === false
                    && $record->isLastActiveSuperAdmin() === false
                    && auth()->user()?->can('update', $record) === true,
            )
            ->action(function (User $record): void {
                self::changeStatus($record, User::STATUS_INACTIVE);
            });
    }

    private static function changeStatus(User $record, string $status): void
    {
        try {
            Gate::authorize('update', $record);
            $record->update(['status' => $status]);

            Notification::make()
                ->title($status === User::STATUS_ACTIVE ? 'تم تفعيل الحساب' : 'تم تعطيل الحساب')
                ->body(
                    $status === User::STATUS_ACTIVE
                        ? 'أصبح الحساب متاحًا وفق الأدوار والصلاحيات الحالية.'
                        : 'تم منع الدخول وإلغاء جلسات تطبيق الجوال المرتبطة بالحساب.',
                )
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('تعذر تحديث حالة الحساب')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    private function __construct()
    {
    }
}
