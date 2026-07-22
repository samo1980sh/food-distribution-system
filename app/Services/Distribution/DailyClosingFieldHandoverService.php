<?php

namespace App\Services\Distribution;

use App\Enums\OperationSource;
use App\Enums\UserRole;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Authorization\AccessScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DailyClosingFieldHandoverService
{
    public function __construct(
        private readonly DailyClosingService $dailyClosingService,
        private readonly AccessScopeService $accessScopeService,
    ) {
    }

    public function openToday(User $user, ?int $routeId = null): DailyClosing
    {
        return DB::transaction(function () use ($user, $routeId): DailyClosing {
            $employeeId = $user->employee()->value('id');

            if ($employeeId === null) {
                throw new RuntimeException('يجب ربط حساب المستخدم بموظف ميداني قبل فتح إغلاق اليوم.');
            }

            $route = $this->resolveRoute($user, (int) $employeeId, $routeId);
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

            if ($route->driver_id === null || $route->sales_representative_id === null) {
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

                if (
                    (int) $existing->driver_id !== (int) $route->driver_id
                    || (int) $existing->sales_representative_id !== (int) $route->sales_representative_id
                ) {
                    throw new RuntimeException('تغيّر فريق الخط بعد فتح إغلاق اليوم. يجب أن تراجع الإدارة الإغلاق الموجود قبل المتابعة.');
                }

                return $existing->load($this->relations());
            }

            $source = $user->hasRole(UserRole::DRIVER->value)
                ? OperationSource::MOBILE_DRIVER
                : OperationSource::MOBILE_SALES;

            $closing = DailyClosing::withoutGlobalScopes()->create([
                'closing_date' => $date,
                'vehicle_id' => $vehicle->getKey(),
                'route_id' => $route->getKey(),
                'warehouse_id' => $warehouse->getKey(),
                'driver_id' => $route->driver_id,
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
            $this->ensureResponsibleEmployee($closing, $user, 'inventory');

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

            $closing = $this->dailyClosingService->refreshTotals($closing);

            $actualCash = round((float) $data['actual_cash_amount'], 2);
            $difference = round($actualCash - (float) $closing->expected_cash_amount, 2);
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

    private function resolveRoute(
        User $user,
        int $employeeId,
        ?int $routeId,
    ): DistributionRoute {
        $query = DistributionRoute::withoutGlobalScopes()
            ->with(['vehicle'])
            ->where('status', 'active')
            ->where(function (Builder $query) use ($user, $employeeId): void {
                $hasCondition = false;

                if ($user->hasRole(UserRole::DRIVER->value)) {
                    $query->where('driver_id', $employeeId);
                    $hasCondition = true;
                }

                if ($user->hasRole(UserRole::SALES_REPRESENTATIVE->value)) {
                    $method = $hasCondition ? 'orWhere' : 'where';
                    $query->{$method}('sales_representative_id', $employeeId);
                }
            });

        $this->accessScopeService->apply($query, $user);

        if ($routeId !== null) {
            $route = $query->whereKey($routeId)->first();

            if ($route === null) {
                throw new RuntimeException('خط التوزيع المحدد غير متاح لهذا المستخدم.');
            }

            return $route;
        }

        $routes = $query->orderBy('id')->limit(2)->get();

        if ($routes->isEmpty()) {
            throw new RuntimeException('لا يوجد خط توزيع فعّال مخصص لهذا المستخدم.');
        }

        if ($routes->count() > 1) {
            throw new RuntimeException('يوجد أكثر من خط متاح. يجب تحديد خط التوزيع لفتح إغلاق اليوم.');
        }

        return $routes->first();
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
    ): void {
        $employeeId = $user->employee()->value('id');
        $responsibleId = $section === 'inventory'
            ? $closing->driver_id
            : $closing->sales_representative_id;

        if ($employeeId === null || (int) $responsibleId !== (int) $employeeId) {
            throw new RuntimeException(
                $section === 'inventory'
                    ? 'جرد السيارة متاح للسائق المسؤول عن هذا الخط فقط.'
                    : 'تسليم النقد متاح لمندوب المبيعات المسؤول عن هذا الخط فقط.',
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
