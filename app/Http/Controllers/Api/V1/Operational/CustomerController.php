<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\CustomerResource;
use App\Models\Customer;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    use BuildsOperationalQueries;

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = Customer::query()->with(['area', 'route']);
        $this->applySearch($query, $request, ['code', 'name', 'owner_name', 'phone', 'mobile', 'address']);
        $query->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status));

        $this->applyIdFilters($query, $request, ['area_id', 'route_id']);

        $paginator = $query
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApiResponse::paginated(
            CustomerResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل العملاء.',
        );
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);
        $customer->loadMissing(['area', 'route']);

        return ApiResponse::success(
            CustomerResource::make($customer)->resolve($request),
            'تم تحميل تفاصيل السجل.',
        );
    }
}
