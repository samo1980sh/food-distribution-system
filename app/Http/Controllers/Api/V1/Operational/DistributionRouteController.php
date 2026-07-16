<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Resources\Api\V1\Operational\DistributionRouteResource;
use App\Models\DistributionRoute;

class DistributionRouteController extends AbstractReadOnlyController
{
    protected function modelClass(): string { return DistributionRoute::class; }
    protected function resourceClass(): string { return DistributionRouteResource::class; }
    protected function indexRelations(): array { return ['area', 'vehicle', 'driver', 'salesRepresentative']; }
    protected function searchColumns(): array { return ['code', 'name']; }
    protected function exactFilters(): array
    {
        return [
            'status' => 'status',
            'area_id' => 'area_id',
            'vehicle_id' => 'vehicle_id',
        ];
    }
    protected function sortColumns(): array { return ['id', 'code', 'name', 'status', 'updated_at']; }
}
