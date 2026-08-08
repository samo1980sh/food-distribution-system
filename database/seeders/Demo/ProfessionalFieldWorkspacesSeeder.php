<?php

namespace Database\Seeders\Demo;

use App\Models\DistributionRoute;
use App\Models\User;
use App\Services\Distribution\DriverFieldOperationService;
use App\Services\Distribution\SalesFieldOperationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ProfessionalFieldWorkspacesSeeder extends Seeder
{
    public function run(): void
    {
        $salesScenarios = [
            ['email' => 'sales@demo.local', 'route' => 'RT-DAM-C'],
            ['email' => 'field.team@demo.local', 'route' => 'RT-DAM-S'],
            ['email' => 'sales.rif@demo.local', 'route' => 'RT-RIF-E'],
        ];

        $driverScenarios = [
            ['email' => 'driver@demo.local', 'route' => 'RT-DAM-C'],
            ['email' => 'field.team@demo.local', 'route' => 'RT-DAM-S'],
            ['email' => 'driver.rif@demo.local', 'route' => 'RT-RIF-E'],
        ];

        $routes = $this->prepareTodayRoutes([
            ...$salesScenarios,
            ...$driverScenarios,
        ]);

        try {
            foreach ($salesScenarios as $scenario) {
                $this->runAs(
                    $scenario['email'],
                    function (User $user) use ($scenario, $routes): void {
                        app(SalesFieldOperationService::class)->openToday(
                            $user,
                            (int) $routes[$scenario['route']]->getKey(),
                        );
                    },
                );
            }

            foreach ($driverScenarios as $scenario) {
                $this->runAs(
                    $scenario['email'],
                    function (User $user) use ($scenario, $routes): void {
                        app(DriverFieldOperationService::class)->openToday(
                            $user,
                            (int) $routes[$scenario['route']]->getKey(),
                        );
                    },
                );
            }
        } finally {
            Auth::logout();
        }
    }

    /**
     * @param list<array{email: string, route: string}> $scenarios
     * @return array<string, DistributionRoute>
     */
    private function prepareTodayRoutes(array $scenarios): array
    {
        $routeCodes = collect($scenarios)
            ->pluck('route')
            ->unique()
            ->values();

        $routes = DistributionRoute::withoutGlobalScopes()
            ->whereIn('code', $routeCodes)
            ->get()
            ->keyBy('code');

        $missing = $routeCodes
            ->reject(fn (string $code): bool => $routes->has($code))
            ->values();

        if ($missing->isNotEmpty()) {
            throw new RuntimeException(
                'تعذر تجهيز خطوط العمل التجريبية: '.$missing->implode(', '),
            );
        }

        $today = strtolower(today()->englishDayOfWeek);

        foreach ($routes as $route) {
            $visitDays = collect($route->visit_days ?? [])
                ->filter(fn (mixed $day): bool => is_string($day) && trim($day) !== '')
                ->map(fn (string $day): string => strtolower(trim($day)))
                ->push($today)
                ->unique()
                ->values()
                ->all();

            $route->forceFill(['visit_days' => $visitDays])->save();
        }

        /** @var array<string, DistributionRoute> $prepared */
        $prepared = $routes->all();

        return $prepared;
    }

    /** @param callable(User): void $callback */
    private function runAs(string $email, callable $callback): void
    {
        Auth::logout();

        $user = User::withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();

        Auth::login($user);

        try {
            $callback($user);
        } finally {
            Auth::logout();
        }
    }
}
