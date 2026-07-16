<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Resources\Api\V1\Operational\AreaResource;
use App\Models\Area;

class AreaController extends AbstractReadOnlyController
{
    protected function modelClass(): string { return Area::class; }
    protected function resourceClass(): string { return AreaResource::class; }
    protected function searchColumns(): array { return ['code', 'name_ar', 'city']; }
    protected function sortColumns(): array { return ['id', 'code', 'name_ar', 'status', 'updated_at']; }
}
