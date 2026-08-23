<?php

namespace App\Services\Distribution;

use App\Enums\PermissionName;
use App\Models\VehicleLoad;
use App\Models\VehicleLoadItem;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VehicleLoadHandoverService
{
    /** @param array<string, mixed> $data */
    public function acknowledge(VehicleLoad $vehicleLoad, array $data): VehicleLoad
    {
        $vehicleLoad = DB::transaction(function () use ($vehicleLoad, $data): VehicleLoad {
            $vehicleLoad = VehicleLoad::query()
                ->with(['items.product'])
                ->lockForUpdate()
                ->findOrFail($vehicleLoad->getKey());

            if (! $vehicleLoad->isApproved()) {
                throw new RuntimeException('لا يمكن تأكيد استلام أمر تحميل غير معتمد.');
            }

            if (! $vehicleLoad->isHandoverPending()) {
                throw new RuntimeException('تم تسجيل استلام أمر التحميل مسبقاً.');
            }

            $submitted = collect($data['items'] ?? [])->keyBy(fn (array $item): int => (int) $item['id']);
            $expectedIds = $vehicleLoad->items->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values();
            $submittedIds = $submitted->keys()->map(fn ($id): int => (int) $id)->sort()->values();

            if ($expectedIds->all() !== $submittedIds->all()) {
                throw new RuntimeException('يجب إرسال نتيجة استلام لكل بند من بنود أمر التحميل.');
            }

            $hasDiscrepancy = false;

            /** @var VehicleLoadItem $item */
            foreach ($vehicleLoad->items as $item) {
                $itemData = (array) $submitted->get((int) $item->id);
                $received = round((float) $itemData['received_quantity'], 3);
                $loaded = round((float) $item->quantity, 3);
                $note = trim((string) Arr::get($itemData, 'note', ''));
                $quantityDifferent = abs($received - $loaded) > 0.0005;
                $documentedIssue = $note !== '';

                if ($quantityDifferent && ! $documentedIssue) {
                    throw new RuntimeException(
                        'كل بند يحتوي فرقاً في الكمية يحتاج إلى ملاحظة توضيحية.',
                    );
                }

                $hasDiscrepancy = $hasDiscrepancy
                    || $quantityDifferent
                    || $documentedIssue;

                $item->forceFill([
                    'received_quantity' => $received,
                    'handover_note' => $documentedIssue ? $note : null,
                ])->saveQuietly();
            }

            $status = (string) $data['handover_status'];
            $handoverNotes = trim((string) ($data['notes'] ?? ''));

            if ($status === 'received' && $hasDiscrepancy) {
                throw new RuntimeException(
                    'لا يمكن تسجيل الاستلام الكامل مع وجود فروقات أو ملاحظات على البنود.',
                );
            }

            if ($status === 'discrepancy' && ! $hasDiscrepancy) {
                throw new RuntimeException(
                    'حالة وجود فروقات تتطلب فرقاً فعلياً أو ملاحظة موثقة على أحد البنود.',
                );
            }

            if ($status === 'discrepancy' && $handoverNotes === '') {
                throw new RuntimeException(
                    'حالة وجود فروقات تتطلب ملاحظة عامة تلخص نتيجة الاستلام.',
                );
            }

            $vehicleLoad->forceFill([
                'handover_status' => $status,
                'handover_notes' => $handoverNotes !== '' ? $handoverNotes : null,
                'handover_by' => Auth::id(),
                'handover_at' => now(),
            ])->save();

            return $vehicleLoad->refresh();
        });

        $this->sendDiscrepancyNotification($vehicleLoad);

        return $vehicleLoad;
    }

    private function sendDiscrepancyNotification(VehicleLoad $vehicleLoad): void
    {
        if ($vehicleLoad->handover_status !== 'discrepancy') {
            return;
        }

        User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->get()
            ->filter(fn (User $user): bool => $user->canManageDistribution()
                || $user->canManageDailyClosings()
                || $user->can(PermissionName::DASHBOARD_VIEW->value))
            ->each(function (User $user) use ($vehicleLoad): void {
                $alreadyNotified = $user->unreadNotifications()
                    ->where('data->vehicle_load_id', $vehicleLoad->getKey())
                    ->where('data->type', 'vehicle_load_handover_discrepancy')
                    ->exists();

                if ($alreadyNotified) {
                    return;
                }

                Notification::make()
                    ->warning()
                    ->title('فروقات عند استلام العهدة')
                    ->body(collect([
                        $vehicleLoad->load_number,
                        $vehicleLoad->vehicle?->name,
                        $vehicleLoad->driver?->name,
                    ])->filter(fn (mixed $value): bool => filled($value))->implode(' - '))
                    ->actions([
                        Action::make('view')
                            ->label('عرض أمر التحميل')
                            ->url(route('filament.admin.resources.vehicle-loads.view', [
                                'record' => $vehicleLoad,
                            ])),
                    ])
                    ->viewData([
                        'vehicle_load_id' => $vehicleLoad->getKey(),
                        'vehicle_load_number' => $vehicleLoad->load_number,
                        'vehicle_name' => $vehicleLoad->vehicle?->name,
                        'driver_name' => $vehicleLoad->driver?->name,
                        'notification_type' => 'vehicle_load_handover_discrepancy',
                    ])
                    ->sendToDatabase($user, true);
            });
    }
}
