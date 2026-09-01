<?php

namespace App\Services\Distribution;

use App\Models\DistributionRoute;
use App\Models\FieldOperationalDayOverride;
use App\Models\User;
use App\Services\Authorization\AccessScopeService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

class FieldRouteAssignmentResolver
{
    public const SCHEDULE_NORMAL = 'normal_schedule';

    public const SCHEDULE_EXCEPTIONAL = 'exceptional_override';

    public const SCHEDULE_NOT_SCHEDULED = 'not_scheduled';

    public const STATUS_READY = 'ready';

    public const STATUS_NO_ASSIGNMENT = 'no_assignment';

    public const STATUS_NOT_SCHEDULED_TODAY = 'not_scheduled_today';

    public const STATUS_ROUTE_SELECTION_REQUIRED = 'route_selection_required';

    public function __construct(
        private readonly AccessScopeService $accessScopeService,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     scheduled_today: bool,
     *     route: DistributionRoute|null,
     *     candidates: Collection<int, DistributionRoute>,
     *     available_count: int,
     *     scheduled_count: int
     * }
     */
    public function resolveRole(
        User $user,
        string $role,
        ?int $selectedRouteId = null,
        ?CarbonInterface $date = null,
    ): array {
        $date ??= today();
        $routes = $this->availableRoutesForRole($user, $role);
        $normalScheduled = $routes
            ->filter(fn (DistributionRoute $route): bool => $this->isScheduledFor($route, $date))
            ->values();
        $operational = $routes
            ->filter(fn (DistributionRoute $route): bool => $this->isOperationalFor($route, $date))
            ->values();

        if ($selectedRouteId !== null) {
            $selected = $routes->firstWhere('id', $selectedRouteId);

            if (! $selected instanceof DistributionRoute) {
                throw (new ModelNotFoundException)->setModel(
                    DistributionRoute::class,
                    [$selectedRouteId],
                );
            }

            $scheduleStatus = $this->scheduleStatus($selected, $date);
            $operationalToday = $scheduleStatus !== self::SCHEDULE_NOT_SCHEDULED;

            return [
                'status' => $operationalToday
                    ? self::STATUS_READY
                    : self::STATUS_NOT_SCHEDULED_TODAY,
                'schedule_status' => $scheduleStatus,
                'scheduled_today' => $scheduleStatus === self::SCHEDULE_NORMAL,
                'operational_today' => $operationalToday,
                'exceptional_operation' => $scheduleStatus === self::SCHEDULE_EXCEPTIONAL,
                'route' => $selected,
                'candidates' => new Collection([$selected]),
                'available_count' => $routes->count(),
                'scheduled_count' => $normalScheduled->count(),
            ];
        }

        if ($routes->isEmpty()) {
            return [
                'status' => self::STATUS_NO_ASSIGNMENT,
                'schedule_status' => self::SCHEDULE_NOT_SCHEDULED,
                'scheduled_today' => false,
                'operational_today' => false,
                'exceptional_operation' => false,
                'route' => null,
                'candidates' => new Collection,
                'available_count' => 0,
                'scheduled_count' => 0,
            ];
        }

        if ($operational->count() === 1) {
            $route = $operational->first();
            $scheduleStatus = $this->scheduleStatus($route, $date);

            return [
                'status' => self::STATUS_READY,
                'schedule_status' => $scheduleStatus,
                'scheduled_today' => $scheduleStatus === self::SCHEDULE_NORMAL,
                'operational_today' => true,
                'exceptional_operation' => $scheduleStatus === self::SCHEDULE_EXCEPTIONAL,
                'route' => $route,
                'candidates' => $operational,
                'available_count' => $routes->count(),
                'scheduled_count' => $normalScheduled->count(),
            ];
        }

        if ($operational->count() > 1) {
            return [
                'status' => self::STATUS_ROUTE_SELECTION_REQUIRED,
                'schedule_status' => self::STATUS_ROUTE_SELECTION_REQUIRED,
                'scheduled_today' => false,
                'operational_today' => true,
                'exceptional_operation' => false,
                'route' => null,
                'candidates' => $operational,
                'available_count' => $routes->count(),
                'scheduled_count' => $normalScheduled->count(),
            ];
        }

        return [
            'status' => self::STATUS_NOT_SCHEDULED_TODAY,
            'schedule_status' => self::SCHEDULE_NOT_SCHEDULED,
            'scheduled_today' => false,
            'operational_today' => false,
            'exceptional_operation' => false,
            'route' => $routes->count() === 1 ? $routes->first() : null,
            'candidates' => $routes,
            'available_count' => $routes->count(),
            'scheduled_count' => 0,
        ];
    }

    public function resolveForClosing(
        User $user,
        ?int $selectedRouteId = null,
        ?CarbonInterface $date = null,
    ): DistributionRoute {
        $date ??= today();
        $routes = $this->availableRoutesForClosing($user);

        if ($selectedRouteId !== null) {
            $selected = $routes->firstWhere('id', $selectedRouteId);

            if (! $selected instanceof DistributionRoute) {
                throw new RuntimeException('خط التوزيع المحدد غير متاح لهذا المستخدم.');
            }

            if (! $this->isOperationalFor($selected, $date)) {
                throw new RuntimeException('خط التوزيع المحدد غير مجدول ولا يملك تصريح تشغيل استثنائي لهذا التاريخ.');
            }

            return $selected;
        }

        $scheduled = $routes
            ->filter(fn (DistributionRoute $route): bool => $this->isOperationalFor($route, $date))
            ->values();

        if ($scheduled->isEmpty()) {
            if ($routes->isEmpty()) {
                throw new RuntimeException('لا يوجد خط توزيع فعّال مخصص لهذا المستخدم.');
            }

            throw new RuntimeException('لا يوجد خط توزيع مجدول لهذا المستخدم في يوم العمل الحالي. حدد الخط صراحة عند الحاجة.');
        }

        if ($scheduled->count() > 1) {
            throw new RuntimeException('يوجد أكثر من خط مجدول اليوم. يجب تحديد خط التوزيع لفتح إغلاق اليوم.');
        }

        return $scheduled->first();
    }

    public function isScheduledFor(
        DistributionRoute $route,
        CarbonInterface $date,
    ): bool {
        $visitDays = collect($route->visit_days ?? [])
            ->filter(fn (mixed $day): bool => is_string($day) && trim($day) !== '')
            ->map(fn (string $day): string => strtolower(trim($day)))
            ->values();

        // Historical routes may not have visit_days. Treat an empty schedule as
        // available every day so this read integration remains backward-compatible.
        if ($visitDays->isEmpty()) {
            return true;
        }

        return $visitDays->contains(strtolower($date->englishDayOfWeek));
    }

    public function scheduleStatus(
        DistributionRoute $route,
        CarbonInterface $date,
    ): string {
        if ($this->isScheduledFor($route, $date)) {
            return self::SCHEDULE_NORMAL;
        }

        return $this->matchingOverrideExists($route, $date)
            ? self::SCHEDULE_EXCEPTIONAL
            : self::SCHEDULE_NOT_SCHEDULED;
    }

    public function isOperationalFor(
        DistributionRoute $route,
        CarbonInterface $date,
    ): bool {
        return $this->scheduleStatus($route, $date) !== self::SCHEDULE_NOT_SCHEDULED;
    }

    private function matchingOverrideExists(
        DistributionRoute $route,
        CarbonInterface $date,
    ): bool {
        if ($route->vehicle_id === null || $route->sales_representative_id === null) {
            return false;
        }

        return FieldOperationalDayOverride::withoutGlobalScopes()
            ->whereDate('operation_date', $date)
            ->where('route_id', $route->getKey())
            ->where('vehicle_id', $route->vehicle_id)
            ->where('sales_representative_id', $route->sales_representative_id)
            ->where('status', 'active')
            ->exists();
    }

    /** @return Collection<int, DistributionRoute> */
    private function availableRoutesForRole(User $user, string $role): Collection
    {
        $employeeId = $user->employee()->value('id');

        if ($employeeId === null || ! $user->hasRole($role)) {
            return new Collection;
        }

        if ($role !== User::ROLE_SALES_REPRESENTATIVE) {
            return new Collection;
        }

        $query = DistributionRoute::withoutGlobalScopes()
            ->with($this->relations())
            ->where('status', 'active')
            ->where('sales_representative_id', $employeeId);

        $this->accessScopeService->apply($query, $user);

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, DistributionRoute> */
    private function availableRoutesForClosing(User $user): Collection
    {
        $employeeId = $user->employee()->value('id');

        if ($employeeId === null) {
            return new Collection;
        }

        if (! $user->hasRole(User::ROLE_SALES_REPRESENTATIVE)) {
            return new Collection;
        }

        $query = DistributionRoute::withoutGlobalScopes()
            ->with($this->relations())
            ->where('status', 'active')
            ->where('sales_representative_id', $employeeId);

        $this->accessScopeService->apply($query, $user);

        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'area',
            'vehicle.warehouse',
            'salesRepresentative',
        ];
    }
}
