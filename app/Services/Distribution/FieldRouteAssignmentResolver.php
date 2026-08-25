<?php

namespace App\Services\Distribution;

use App\Models\DistributionRoute;
use App\Models\User;
use App\Services\Authorization\AccessScopeService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

class FieldRouteAssignmentResolver
{
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
        $scheduled = $routes
            ->filter(fn (DistributionRoute $route): bool => $this->isScheduledFor($route, $date))
            ->values();

        if ($selectedRouteId !== null) {
            $selected = $routes->firstWhere('id', $selectedRouteId);

            if (! $selected instanceof DistributionRoute) {
                throw (new ModelNotFoundException)->setModel(
                    DistributionRoute::class,
                    [$selectedRouteId],
                );
            }

            $scheduledToday = $this->isScheduledFor($selected, $date);

            return [
                'status' => $scheduledToday
                    ? self::STATUS_READY
                    : self::STATUS_NOT_SCHEDULED_TODAY,
                'scheduled_today' => $scheduledToday,
                'route' => $selected,
                'candidates' => new Collection([$selected]),
                'available_count' => $routes->count(),
                'scheduled_count' => $scheduled->count(),
            ];
        }

        if ($routes->isEmpty()) {
            return [
                'status' => self::STATUS_NO_ASSIGNMENT,
                'scheduled_today' => false,
                'route' => null,
                'candidates' => new Collection,
                'available_count' => 0,
                'scheduled_count' => 0,
            ];
        }

        if ($scheduled->count() === 1) {
            return [
                'status' => self::STATUS_READY,
                'scheduled_today' => true,
                'route' => $scheduled->first(),
                'candidates' => $scheduled,
                'available_count' => $routes->count(),
                'scheduled_count' => 1,
            ];
        }

        if ($scheduled->count() > 1) {
            return [
                'status' => self::STATUS_ROUTE_SELECTION_REQUIRED,
                'scheduled_today' => true,
                'route' => null,
                'candidates' => $scheduled,
                'available_count' => $routes->count(),
                'scheduled_count' => $scheduled->count(),
            ];
        }

        return [
            'status' => self::STATUS_NOT_SCHEDULED_TODAY,
            'scheduled_today' => false,
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

            return $selected;
        }

        $scheduled = $routes
            ->filter(fn (DistributionRoute $route): bool => $this->isScheduledFor($route, $date))
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
