<?php

namespace App\Services\Distribution;

use App\Enums\OperationSource;
use App\Enums\UserRole;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\DriverJourney;
use App\Models\SalesJourney;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DailyClosingFieldHandoverService
{
    public function __construct(
        private readonly DailyClosingService $dailyClosingService,
        private readonly FieldRouteAssignmentResolver $routeAssignmentResolver,
    ) {}

    public function openToday(User $user, ?int $routeId = null): DailyClosing
    {
        return DB::transaction(function () use ($user, $routeId): DailyClosing {
            $employeeId = $user->employee()->value('id');

            if ($employeeId === null) {
                throw new RuntimeException('يجب ربط حساب المستخدم بموظف ميداني قبل فتح إغلاق اليوم.');
            }

            $route = $this->routeAssignmentResolver->resolveForClosing(
                $user,
                $routeId,
            );
            $vehicle = $route->vehicle;

            if ($vehicle === null || $vehicle->status !== 'active') {
                throw new RuntimeException('خط التوزيع لا يرتبط بسيارة فعّالة.');
            }

            $warehouse = Warehouse::withoutGlobalScopes()
                ->where('vehicle_id', $vehicle->getKey())
                ->where('type', 'vehicle')
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($warehouse === null) {
                throw new RuntimeException('السيارة المحددة لا تملك مستودع سيارة فعّالًا.');
            }

            $ownerRole = $this->fieldOwnerRole($user, $route, (int) $employeeId);

            if ($ownerRole === UserRole::SALES_REPRESENTATIVE && $route->sales_representative_id === null) {
                throw new RuntimeException('يجب تعيين مندوب المبيعات على خط التوزيع قبل فتح الإغلاق الميداني.');
            }

            if (
                $ownerRole === UserRole::DRIVER
                && ($route->driver_id === null || $route->sales_representative_id === null)
            ) {
                throw new RuntimeException('يجب تعيين السائق ومندوب المبيعات على خط التوزيع قبل فتح الإغلاق الميداني.');
            }

            $date = today()->toDateString();
            $existing = DailyClosing::withoutGlobalScopes()
                ->whereDate('closing_date', $date)
                ->where('warehouse_id', $warehouse->getKey())
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (! $existing->isFieldWorkflow()) {
                    throw new RuntimeException('يوجد إغلاق إداري فعّال لهذا اليوم ومستودع السيارة. راجع الإدارة قبل المتابعة.');
                }

                if (
                    (int) $existing->route_id !== (int) $route->getKey()
                    || (int) $existing->vehicle_id !== (int) $vehicle->getKey()
                ) {
                    throw new RuntimeException('إغلاق اليوم الموجود مرتبط بسياق تشغيلي مختلف عن الخط المحدد.');
                }

                $representativeChanged = (int) $existing->sales_representative_id
                    !== (int) $route->sales_representative_id;
                $legacyDriverChanged = $ownerRole === UserRole::DRIVER
                    && (int) $existing->driver_id !== (int) $route->driver_id;

                if ($representativeChanged || $legacyDriverChanged) {
                    throw new RuntimeException('تغيّر فريق الخط بعد فتح إغلاق اليوم. يجب أن تراجع الإدارة الإغلاق الموجود قبل المتابعة.');
                }

                return $existing->load($this->relations());
            }

            $representativeWorkflow = $ownerRole === UserRole::SALES_REPRESENTATIVE;
            $source = $representativeWorkflow
                ? OperationSource::MOBILE_SALES
                : OperationSource::MOBILE_DRIVER;

            $closing = DailyClosing::withoutGlobalScopes()->create([
                'closing_date' => $date,
                'vehicle_id' => $vehicle->getKey(),
                'route_id' => $route->getKey(),
                'warehouse_id' => $warehouse->getKey(),
                'driver_id' => $representativeWorkflow ? null : $route->driver_id,
                'sales_representative_id' => $route->sales_representative_id,
                'field_workflow' => true,
                'status' => 'draft',
                'actual_cash_amount' => 0,
                'created_by' => $user->getKey(),
                'operation_source' => $source,
            ]);

            $closing = $this->dailyClosingService->refreshTotals($closing);
            $closing->wasRecentlyCreated = true;

            return $closing->load($this->relations());
        });
    }

    /** @param array<string, mixed> $data */
    public function submitInventory(
        DailyClosing $dailyClosing,
        User $user,
        array $data,
    ): DailyClosing {
        return DB::transaction(function () use ($dailyClosing, $user, $data): DailyClosing {
            $closing = DailyClosing::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($dailyClosing->getKey());

            $this->ensureFieldDraft($closing);
            $ownerRole = $this->ensureResponsibleEmployee($closing, $user, 'inventory');

            if ($ownerRole === UserRole::SALES_REPRESENTATIVE) {
                $this->ensureSalesJourneyCompleted($closing);
            } else {
                $this->ensureDriverJourneyCompletedWhenPresent($closing);
            }

            if ($closing->inventorySubmitted()) {
                throw new RuntimeException('تم تسليم جرد السيارة سابقاً ولا يمكن استبداله من التطبيق الميداني.');
            }

            $closing = $this->dailyClosingService->refreshTotals($closing);
            $closing->load('items');

            $submitted = collect($data['items'] ?? [])
                ->keyBy(fn (array $item): int => (int) $item['product_id']);
            $expectedIds = $closing->items
                ->pluck('product_id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values();
            $submittedIds = $submitted->keys()
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values();

            if ($expectedIds->all() !== $submittedIds->all()) {
                throw new RuntimeException('يجب إرسال الجرد الفعلي لجميع مواد إغلاق اليوم دون إضافة أو حذف مواد.');
            }

            foreach ($closing->items as $item) {
                $itemData = (array) $submitted->get((int) $item->product_id);
                $actual = round((float) $itemData['actual_quantity'], 3);
                $difference = round($actual - (float) $item->expected_quantity, 3);
                $notes = trim((string) Arr::get($itemData, 'notes', ''));

                if (abs($difference) >= 0.0005 && $notes === '') {
                    throw new RuntimeException('يجب توضيح سبب فرق الجرد لكل مادة تختلف كميتها الفعلية عن المتوقعة.');
                }

                $item->forceFill([
                    'actual_quantity' => $actual,
                    'difference_quantity' => $difference,
                    'notes' => $notes !== '' ? $notes : null,
                ])->save();
            }

            $closing->forceFill([
                'inventory_submitted_by' => $user->getKey(),
                'inventory_submitted_at' => now(),
            ])->save();

            return $closing->refresh()->load($this->relations());
        });
    }

    /** @param array<string, mixed> $data */
    public function submitCash(
        DailyClosing $dailyClosing,
        User $user,
        array $data,
    ): DailyClosing {
        return DB::transaction(function () use ($dailyClosing, $user, $data): DailyClosing {
            $closing = DailyClosing::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($dailyClosing->getKey());

            $this->ensureFieldDraft($closing);
            $this->ensureResponsibleEmployee($closing, $user, 'cash');

            $this->ensureSalesJourneyCompleted($closing);

            if ($closing->cashSubmitted()) {
                throw new RuntimeException('تم تسليم النقد سابقاً ولا يمكن استبداله من التطبيق الميداني.');
            }

            $closing = $this->dailyClosingService->refreshTotals($closing);

            $actualCash = round((float) $data['actual_cash_amount'], 2);
            $expectedCashHandover = max((float) $closing->expected_cash_amount, 0);
            $difference = round($actualCash - $expectedCashHandover, 2);
            $notes = trim((string) ($data['cash_notes'] ?? ''));

            if (abs($difference) >= 0.005 && $notes === '') {
                throw new RuntimeException('يجب توضيح سبب فرق الصندوق قبل تسليم النقد.');
            }

            $closing->forceFill([
                'actual_cash_amount' => $actualCash,
                'cash_difference' => $difference,
                'cash_notes' => $notes !== '' ? $notes : null,
                'cash_submitted_by' => $user->getKey(),
                'cash_submitted_at' => now(),
            ])->save();

            return $closing->refresh()->load($this->relations());
        });
    }

    private function ensureFieldDraft(DailyClosing $closing): void
    {
        if (! $closing->isFieldWorkflow()) {
            throw new RuntimeException('هذا الإغلاق إداري ولا يقبل التسليم من التطبيق الميداني.');
        }

        if (! $closing->isDraft()) {
            throw new RuntimeException('لا يمكن تعديل تسليم إغلاق يوم ليس بحالة مسودة.');
        }
    }

    private function ensureResponsibleEmployee(
        DailyClosing $closing,
        User $user,
        string $section,
    ): UserRole {
        $employeeId = $user->employee()->value('id');

        if (
            $employeeId !== null
            && $user->hasRole(UserRole::SALES_REPRESENTATIVE->value)
            && (int) $closing->sales_representative_id === (int) $employeeId
        ) {
            return UserRole::SALES_REPRESENTATIVE;
        }

        if (
            $section === 'inventory'
            && $employeeId !== null
            && $user->hasRole(UserRole::DRIVER->value)
            && (int) $closing->driver_id === (int) $employeeId
        ) {
            return UserRole::DRIVER;
        }

        throw new RuntimeException(
            $section === 'inventory'
                ? 'جرد السيارة متاح للمندوب المسؤول عن الخط، مع الإبقاء على توافق السائق للسجلات القديمة.'
                : 'تسليم النقد متاح لمندوب المبيعات المسؤول عن هذا الخط فقط.',
        );
    }

    private function fieldOwnerRole(
        User $user,
        DistributionRoute $route,
        int $employeeId,
    ): UserRole {
        if (
            $user->hasRole(UserRole::SALES_REPRESENTATIVE->value)
            && (int) $route->sales_representative_id === $employeeId
        ) {
            return UserRole::SALES_REPRESENTATIVE;
        }

        if (
            $user->hasRole(UserRole::DRIVER->value)
            && (int) $route->driver_id === $employeeId
        ) {
            return UserRole::DRIVER;
        }

        throw new RuntimeException('المستخدم ليس مسؤولاً عن السياق الميداني لخط التوزيع المحدد.');
    }

    private function ensureDriverJourneyCompletedWhenPresent(DailyClosing $closing): void
    {
        $journey = DriverJourney::withoutGlobalScopes()
            ->whereDate('journey_date', $closing->closing_date)
            ->where('route_id', $closing->route_id)
            ->where('driver_id', $closing->driver_id)
            ->lockForUpdate()
            ->first();

        if ($journey !== null && ! $journey->isCompleted()) {
            throw new RuntimeException(
                'يجب إنهاء رحلة السائق ومعالجة جميع التسليمات قبل تسليم جرد السيارة.',
            );
        }
    }

    private function ensureSalesJourneyCompleted(DailyClosing $closing): void
    {
        $journey = SalesJourney::withoutGlobalScopes()
            ->whereDate('journey_date', $closing->closing_date)
            ->where('route_id', $closing->route_id)
            ->where('sales_representative_id', $closing->sales_representative_id)
            ->lockForUpdate()
            ->first();

        $requiresUnifiedJourney = $closing->operation_source === OperationSource::MOBILE_SALES
            || $closing->driver_id === null;

        if ($journey === null && $requiresUnifiedJourney) {
            throw new RuntimeException(
                'يجب إكمال رحلة المندوب الموحدة قبل تسليم الجرد أو النقد.',
            );
        }

        if ($journey !== null && ! $journey->isCompleted()) {
            throw new RuntimeException(
                'يجب إنهاء رحلة المبيعات وجميع الزيارات قبل تسليم النقد.',
            );
        }
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'vehicle.warehouse',
            'route',
            'warehouse.vehicle',
            'driver',
            'salesRepresentative',
            'inventorySubmitter',
            'cashSubmitter',
            'items.product.category',
            'items.product.unit',
        ];
    }
}
