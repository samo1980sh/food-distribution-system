<?php

namespace App\Support\Filament;

use App\Models\User;
use App\Services\MasterDataDeletionGuard;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\LazyCollection;

final class MasterDataBulkDeleteAction
{
    /**
     * Do not register a bulk action at all for unauthorized users. This also keeps
     * Filament row-selection checkboxes out of non-super-admin Master Data tables.
     *
     * @param class-string<Model> $modelClass
     * @return list<BulkAction>
     */
    public static function actionsFor(string $modelClass): array
    {
        if (! self::isAuthorized($modelClass)) {
            return [];
        }

        return [self::make($modelClass)];
    }

    /** @param class-string<Model> $modelClass */
    public static function make(string $modelClass): BulkAction
    {
        return BulkAction::make('master_data_bulk_delete')
            ->label('حذف المحدد')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('تأكيد حذف السجلات المحددة')
            ->modalDescription('سيتم حذف السجلات غير المرتبطة فقط. أي سجل مرتبط ببيانات تشغيلية أو تاريخية سيبقى محفوظًا مع إظهار سبب المنع.')
            ->modalSubmitActionLabel('نعم، حذف المحدد')
            ->visible(fn (): bool => self::isAuthorized($modelClass))
            ->chunkSelectedRecords(250)
            ->action(function (Collection|LazyCollection $records) use ($modelClass): void {
                $user = auth()->user();

                if (! $user instanceof User || ! self::isAuthorized($modelClass, $user)) {
                    Notification::make()
                        ->title('غير مسموح بالحذف الجماعي')
                        ->body('هذه العملية متاحة لمدير النظام الأعلى فقط وضمن سياسات الحذف الحالية.')
                        ->danger()
                        ->send();

                    return;
                }

                $guard = app(MasterDataDeletionGuard::class);
                $deletedCount = 0;
                $blocked = [];

                foreach ($records as $record) {
                    if (! $record instanceof Model || ! $record instanceof $modelClass) {
                        continue;
                    }

                    $label = $guard->recordLabel($record);

                    if (! Gate::forUser($user)->allows('delete', $record)) {
                        $blocked[] = $label.': لا تسمح السياسة الحالية بحذف هذا السجل.';
                        continue;
                    }

                    if ($reason = $guard->reason($record)) {
                        $blocked[] = $label.': '.$reason;
                        continue;
                    }

                    try {
                        if ($record->delete()) {
                            $deletedCount++;
                        } else {
                            $blocked[] = $label.': تعذر حذف السجل دون تغيير أي بيانات مرتبطة.';
                        }
                    } catch (QueryException) {
                        $blocked[] = $label.': '.$guard->databaseConstraintReason();
                    }
                }

                if ($deletedCount > 0) {
                    Notification::make()
                        ->title('تم حذف '.$deletedCount.' سجل بنجاح')
                        ->body('تم حذف السجلات المسموح بها فقط، مع إبقاء أي سجل محمي بعلاقة مرجعية.')
                        ->success()
                        ->send();
                }

                if ($blocked !== []) {
                    $visibleReasons = array_slice($blocked, 0, 6);
                    $body = implode(' | ', $visibleReasons);

                    if (count($blocked) > count($visibleReasons)) {
                        $body .= ' | وهناك '.(count($blocked) - count($visibleReasons)).' سجل إضافي لم يُحذف.';
                    }

                    Notification::make()
                        ->title('لم يتم حذف '.count($blocked).' سجل محمي')
                        ->body($body)
                        ->warning()
                        ->persistent()
                        ->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }

    /** @param class-string<Model> $modelClass */
    private static function isAuthorized(string $modelClass, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User
            && $user->isSuperAdmin()
            && Gate::forUser($user)->allows('deleteAny', $modelClass);
    }
}
