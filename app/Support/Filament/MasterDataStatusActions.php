<?php

namespace App\Support\Filament;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class MasterDataStatusActions
{
    public static function activate(string $subjectLabel): Action
    {
        return self::transition(
            name: 'activate',
            label: 'إعادة التفعيل',
            subjectLabel: $subjectLabel,
            fromStatus: 'inactive',
            toStatus: 'active',
            icon: 'heroicon-o-check-circle',
            color: 'success',
            confirmationTitle: 'إعادة تفعيل '.$subjectLabel,
            confirmationDescription: 'سيعود السجل للظهور في قوائم الاختيار والعمليات الجديدة وفق الصلاحيات ونطاقات الوصول.',
            successTitle: 'تمت إعادة التفعيل',
        );
    }

    public static function deactivate(string $subjectLabel): Action
    {
        return self::transition(
            name: 'deactivate',
            label: 'تعطيل',
            subjectLabel: $subjectLabel,
            fromStatus: 'active',
            toStatus: 'inactive',
            icon: 'heroicon-o-no-symbol',
            color: 'danger',
            confirmationTitle: 'تعطيل '.$subjectLabel,
            confirmationDescription: 'سيُحفظ السجل وتاريخه، لكنه لن يكون متاحًا للعمليات الجديدة. يمكن إعادة تفعيله لاحقًا.',
            successTitle: 'تم تعطيل السجل',
        );
    }

    private static function transition(
        string $name,
        string $label,
        string $subjectLabel,
        string $fromStatus,
        string $toStatus,
        string $icon,
        string $color,
        string $confirmationTitle,
        string $confirmationDescription,
        string $successTitle,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->modalHeading($confirmationTitle)
            ->modalDescription($confirmationDescription)
            ->modalSubmitActionLabel($label)
            ->visible(
                fn (Model $record): bool => $record->getAttribute('status') === $fromStatus
                    && auth()->user()?->can('update', $record) === true,
            )
            ->action(function (Model $record) use ($toStatus, $subjectLabel, $successTitle): void {
                try {
                    Gate::authorize('update', $record);
                    $record->forceFill(['status' => $toStatus])->save();

                    Notification::make()
                        ->title($successTitle)
                        ->body('تم تحديث حالة '.$subjectLabel.' مع المحافظة على السجل التاريخي المرتبط به.')
                        ->success()
                        ->send();
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('تعذر تحديث الحالة')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    private function __construct()
    {
    }
}
