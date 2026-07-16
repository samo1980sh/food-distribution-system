<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Resources\Api\V1\Operational\ProductResource;
use App\Models\Product;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    use BuildsOperationalQueries;

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = Product::query()->with(['category', 'unit']);
        $this->applySearch($query, $request, ['sku', 'barcode', 'name_ar']);
        $query->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status));



        $paginator = $query
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApiResponse::paginated(
            ProductResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل المنتجات.',
        );
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('view', $product);
        $product->loadMissing(['category', 'unit']);

        return ApiResponse::success(
            ProductResource::make($product)->resolve($request),
            'تم تحميل تفاصيل السجل.',
        );
    }
}
