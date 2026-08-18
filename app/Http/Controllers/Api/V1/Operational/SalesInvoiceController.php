<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Api\V1\Operational\Concerns\BuildsOperationalQueries;
use App\Http\Controllers\Api\V1\Operational\Concerns\HandlesOperationalWriteResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Operational\CancelOperationalDocumentRequest;
use App\Http\Requests\Api\V1\Operational\OperationalIndexRequest;
use App\Http\Requests\Api\V1\Operational\SalesInvoiceWriteRequest;
use App\Http\Resources\Api\V1\Operational\SalesInvoiceResource;
use App\Models\SalesInvoice;
use App\Models\SalesVisit;
use App\Services\Api\MobileOperationalWriteService;
use App\Services\Sales\SalesInvoiceService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SalesInvoiceController extends Controller
{
    use BuildsOperationalQueries;
    use HandlesOperationalWriteResponses;

    private const RELATIONS = [
        'customer.area',
        'customer.route',
        'vehicle.warehouse',
        'route',
        'warehouse.vehicle',
        'salesRepresentative',
        'items.product.category',
        'items.product.unit',
    ];

    public function index(OperationalIndexRequest $request): JsonResponse
    {
        $query = SalesInvoice::query()->with(array_slice(self::RELATIONS, 0, 6));
        $this->applySearch($query, $request, ['invoice_number', 'client_reference', 'notes']);
        $query->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status));
        $this->applyDateRange($query, $request, 'invoice_date');
        $this->applyIdFilters($query, $request, ['customer_id', 'route_id', 'vehicle_id', 'warehouse_id']);

        $paginator = $query
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ApiResponse::paginated(
            SalesInvoiceResource::collection($paginator->getCollection())->resolve($request),
            $paginator,
            'تم تحميل فواتير المبيعات.',
        );
    }

    public function show(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        Gate::authorize('view', $salesInvoice);

        return $this->recordResponse($request, $salesInvoice, 'تم تحميل تفاصيل السجل.');
    }

    public function store(
        SalesInvoiceWriteRequest $request,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        return $this->handleOperationalWrite(function () use ($request, $writeService): JsonResponse {
            $result = $writeService->createSalesInvoice($request->validated());
            Gate::authorize('view', $result->record);

            return $this->recordResponse(
                $request,
                $result->record,
                $result->replayed ? 'تمت إعادة الفاتورة المسجلة سابقاً.' : 'تم إنشاء مسودة فاتورة المبيعات.',
                $result->replayed ? 200 : 201,
                ['idempotency' => ['replayed' => $result->replayed]],
            );
        });
    }

    public function update(
        SalesInvoiceWriteRequest $request,
        SalesInvoice $salesInvoice,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $writeService->updateSalesInvoice($salesInvoice, $request->validated()),
            'تم تحديث مسودة فاتورة المبيعات.',
        ));
    }

    public function destroy(
        Request $request,
        SalesInvoice $salesInvoice,
        MobileOperationalWriteService $writeService,
    ): JsonResponse {
        Gate::authorize('delete', $salesInvoice);

        return $this->handleOperationalWrite(function () use ($salesInvoice, $writeService): JsonResponse {
            $id = (int) $salesInvoice->id;
            $writeService->deleteRecord($salesInvoice);

            return ApiResponse::success(['id' => $id], 'تم حذف مسودة فاتورة المبيعات.');
        });
    }

    public function confirm(
        Request $request,
        SalesInvoice $salesInvoice,
        SalesInvoiceService $service,
    ): JsonResponse {
        Gate::authorize('confirm', $salesInvoice);

        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $service->confirm($salesInvoice),
            'تم اعتماد فاتورة المبيعات.',
        ));
    }

    public function cancel(
        CancelOperationalDocumentRequest $request,
        SalesInvoice $salesInvoice,
        SalesInvoiceService $service,
    ): JsonResponse {
        return $this->handleOperationalWrite(fn (): JsonResponse => $this->recordResponse(
            $request,
            $service->cancel($salesInvoice, $request->validated('reason')),
            'تم إلغاء فاتورة المبيعات.',
        ));
    }

    /** @param array<string, mixed> $meta */
    private function recordResponse(
        Request $request,
        SalesInvoice $invoice,
        string $message,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $invoice->loadMissing(self::RELATIONS);

        if ($invoice->status === 'draft' && $invoice->sales_visit_id === null) {
            $resolvedVisitId = $this->resolveLegacyDraftSalesVisitId($invoice);

            if ($resolvedVisitId !== null) {
                $invoice->setAttribute('sales_visit_id', $resolvedVisitId);
            }
        }

        return ApiResponse::success(
            SalesInvoiceResource::make($invoice)->resolve($request),
            $message,
            $status,
            $meta,
        );
    }

    private function resolveLegacyDraftSalesVisitId(SalesInvoice $invoice): ?int
    {
        if (
            $invoice->customer_id === null
            || $invoice->route_id === null
            || $invoice->warehouse_id === null
            || $invoice->invoice_date === null
        ) {
            return null;
        }

        $query = SalesVisit::withoutGlobalScopes()
            ->where('customer_id', (int) $invoice->customer_id)
            ->where('route_id', (int) $invoice->route_id)
            ->where('warehouse_id', (int) $invoice->warehouse_id)
            ->whereHas('journey', fn ($journeyQuery) => $journeyQuery
                ->whereDate('journey_date', $invoice->invoice_date->toDateString()));

        if ($invoice->sales_representative_id === null) {
            $query->whereNull('sales_representative_id');
        } else {
            $query->where(
                'sales_representative_id',
                (int) $invoice->sales_representative_id,
            );
        }

        $candidateIds = $query
            ->orderByDesc('id')
            ->limit(2)
            ->pluck('id');

        return $candidateIds->count() === 1
            ? (int) $candidateIds->first()
            : null;
    }
}
