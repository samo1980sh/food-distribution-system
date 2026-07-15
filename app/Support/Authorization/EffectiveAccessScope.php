<?php

namespace App\Support\Authorization;

use App\Enums\UserRole;

final readonly class EffectiveAccessScope
{
    /**
     * @param list<int> $areaIds
     * @param list<int> $routeIds
     * @param list<int> $vehicleIds
     * @param list<int> $warehouseIds
     * @param list<int> $employeeIds
     */
    public function __construct(
        public ?UserRole $role,
        public bool $unrestricted,
        public array $areaIds = [],
        public array $routeIds = [],
        public array $vehicleIds = [],
        public array $warehouseIds = [],
        public array $employeeIds = [],
        public ?int $employeeId = null,
    ) {
    }

    public function hasAssignments(): bool
    {
        return $this->unrestricted
            || $this->areaIds !== []
            || $this->routeIds !== []
            || $this->vehicleIds !== []
            || $this->warehouseIds !== []
            || $this->employeeIds !== [];
    }
}
