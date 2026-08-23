<?php

namespace App\Services\Distribution;

use App\Enums\OperationSource;
use App\Exceptions\Api\OperationalApiException;
use App\Models\DistributionRoute;
use App\Models\DriverDelivery;
use App\Models\DriverJourney;
use App\Models\User;
use App\Models\VehicleLoad;
use App\Services\Support\DocumentNumberService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DriverFieldOperationService
{
    public function __construct(
        private readonly FieldRouteAssignmentResolver $routeResolver,
    ) {
    }

    public function openToday(User $user, ?int $routeId = null): DriverJourney
    {
        $resolution = $this->routeResolver->resolveRole(
            $user,
            User::ROLE_DRIVER,
            $routeId,
            today(),
        );

        $route = $resolution['route'];

        if (! $route instanceof DistributionRoute) {
            throw new RuntimeException(match ($resolution['status']) {
                FieldRouteAssignmentResolver::STATUS_ROUTE_SELECTION_REQUIRED => 'يوجد أكثر من خط مجدول اليوم. يجب تحديد خط التوزيع.',
                FieldRouteAssignmentResolver::STATUS_NOT_SCHEDULED_TODAY => 'لا يوجد خط توزيع مجدول للسائق في يوم العمل الحالي.',
                default => 'لا يوجد خط توزيع فعّال مخصص لهذا السائق.',
            });
        }

        if ($resolution['status'] !== FieldRouteAssignmentResolver::STATUS_READY) {
            throw new RuntimeException('خط التوزيع المحدد غير مجدول للسائق اليوم.');
        }

        $vehicle = $route->vehicle;
        $warehouse = $vehicle?->warehouse;

        if ($vehicle === null || $vehicle->status !== 'active') {
            throw new RuntimeException('خط التوزيع لا يحتوي سيارة فعّالة.');
        }

        if ($warehouse === null || $warehouse->type !== 'vehicle' || $warehouse->status !== 'active') {
            throw new RuntimeException('خط التوزيع لا يحتوي مستودع سيارة فعّال.');
        }

        return DB::transaction(function () use ($route, $vehicle, $warehouse): DriverJourney {
            $created = false;
            $journey = DriverJourney::withoutGlobalScopes()
                ->whereDate('journey_date', today())
                ->where('route_id', $route->getKey())
                ->where('driver_id', $route->driver_id)
                ->lockForUpdate()
                ->first();

            if ($journey === null) {
                $journey = DriverJourney::query()->create([
                    'journey_date' => today()->toDateString(),
                    'route_id' => $route->getKey(),
                    'vehicle_id' => $vehicle->getKey(),
                    'warehouse_id' => $warehouse->getKey(),
                    'driver_id' => $route->driver_id,
                    'sales_representative_id' => $route->sales_representative_id,
                    'status' => 'ready',
                    'created_by' => Auth::id(),
                    'operation_source' => OperationSource::MOBILE_DRIVER,
                ]);
                $created = true;
            }

            $journey = $this->loadJourney($journey);
            $journey->wasRecentlyCreated = $created;

            return $journey;
        });
    }

    public function start(DriverJourney $journey, array $payload): DriverJourney
    {
        return DB::transaction(function () use ($journey, $payload): DriverJourney {
            $journey = DriverJourney::query()->lockForUpdate()->findOrFail($journey->getKey());

            if (! $journey->isReady()) {
                throw new RuntimeException('لا يمكن بدء رحلة ليست بحالة جاهزة.');
            }

            app(DailyClosingGuard::class)->ensureOpen(
                $journey->journey_date,
                (int) $journey->warehouse_id,
            );

            $pendingLoadExists = VehicleLoad::withoutGlobalScopes()
                ->whereDate('load_date', $journey->journey_date)
                ->where('route_id', $journey->route_id)
                ->where('driver_id', $journey->driver_id)
                ->where('status', 'approved')
                ->where('handover_status', 'pending')
                ->exists();

            if ($pendingLoadExists) {
                throw new OperationalApiException(
                    'يجب استلام جميع أوامر التحميل المعتمدة قبل بدء الرحلة.',
                    'vehicle_load_handover_pending',
                    409,
                );
            }

            $journey->forceFill([
                'status' => 'in_progress',
                'start_odometer' => $payload['start_odometer'],
                'start_notes' => $payload['notes'] ?? null,
                'started_at' => now(),
            ])->save();

            return $this->loadJourney($journey);
        });
    }

    public function submitOutcome(DriverDelivery $delivery, array $payload): DriverDelivery
    {
        return DB::transaction(function () use ($delivery, $payload): DriverDelivery {
            $delivery = DriverDelivery::query()
                ->with(['journey', 'items'])
                ->lockForUpdate()
                ->findOrFail($delivery->getKey());

            if (! $delivery->journey?->isInProgress()) {
                throw new RuntimeException('لا يمكن تسجيل نتيجة تسليم قبل بدء الرحلة أو بعد إنهائها.');
            }

            if (! $delivery->isPending()) {
                throw new RuntimeException('تم تسجيل نتيجة هذا التسليم مسبقاً.');
            }

            app(DailyClosingGuard::class)->ensureOpen(
                $delivery->journey->journey_date,
                (int) $delivery->warehouse_id,
            );

            $submittedItems = collect($payload['items'])->keyBy('sales_invoice_item_id');

            if ($submittedItems->count() !== $delivery->items->count()) {
                throw new OperationalApiException(
                    'يجب إرسال نتيجة كل مادة موجودة في التسليم.',
                    'delivery_items_incomplete',
                    422,
                );
            }

            $deliveredTotal = 0.0;
            $returnedTotal = 0.0;

            foreach ($delivery->items as $item) {
                $submitted = $submittedItems->get($item->sales_invoice_item_id);

                if (! is_array($submitted)) {
                    throw new OperationalApiException(
                        'يوجد عنصر غير تابع لهذا التسليم أو عنصر مفقود.',
                        'delivery_item_not_found',
                        422,
                    );
                }

                $delivered = round((float) $submitted['delivered_quantity'], 3);
                $returned = round((float) $submitted['returned_quantity'], 3);
                $expected = round((float) $item->expected_quantity, 3);

                if (abs(($delivered + $returned) - $expected) > 0.0005) {
                    throw new OperationalApiException(
                        'مجموع الكمية المسلمة والمرتجعة يجب أن يساوي الكمية المتوقعة لكل مادة.',
                        'delivery_quantity_mismatch',
                        422,
                        ['sales_invoice_item_id' => [(int) $item->sales_invoice_item_id]],
                    );
                }

                $item->forceFill([
                    'delivered_quantity' => $delivered,
                    'returned_quantity' => $returned,
                    'notes' => $submitted['notes'] ?? null,
                ])->save();

                $deliveredTotal += $delivered;
                $returnedTotal += $returned;
            }

            $outcome = (string) $payload['outcome'];
            $expectedTotal = round((float) $delivery->expected_quantity, 3);
            $deliveredTotal = round($deliveredTotal, 3);
            $returnedTotal = round($returnedTotal, 3);

            match ($outcome) {
                'delivered' => $this->assertDeliveredOutcome($deliveredTotal, $returnedTotal, $expectedTotal),
                'partial' => $this->assertPartialOutcome($deliveredTotal, $returnedTotal),
                'failed' => $this->assertFailedOutcome($deliveredTotal, $returnedTotal, $expectedTotal),
                default => throw new RuntimeException('نتيجة التسليم غير مدعومة.'),
            };

            $delivery->forceFill([
                'status' => $outcome,
                'delivered_quantity' => $deliveredTotal,
                'returned_quantity' => $returnedTotal,
                'return_required' => $returnedTotal > 0,
                'recipient_name' => $payload['recipient_name'] ?? null,
                'recipient_phone' => $payload['recipient_phone'] ?? null,
                'latitude' => $payload['latitude'] ?? null,
                'longitude' => $payload['longitude'] ?? null,
                'proof_note' => $payload['proof_note'] ?? null,
                'failure_reason' => $payload['failure_reason'] ?? null,
                'outcome_submitted_at' => now(),
                'outcome_submitted_by' => Auth::id(),
            ])->save();

            // The journey resource exposes delivery summaries. Touching the
            // parent emits a pull-sync change so cached journey projections
            // do not retain stale pending/delivered counts.
            $delivery->journey->touch();

            return $this->loadDelivery($delivery);
        });
    }

    public function finish(DriverJourney $journey, array $payload): DriverJourney
    {
        return DB::transaction(function () use ($journey, $payload): DriverJourney {
            $journey = DriverJourney::query()->lockForUpdate()->findOrFail($journey->getKey());

            if (! $journey->isInProgress()) {
                throw new RuntimeException('لا يمكن إنهاء رحلة ليست قيد التنفيذ.');
            }

            app(DailyClosingGuard::class)->ensureOpen(
                $journey->journey_date,
                (int) $journey->warehouse_id,
            );

            if ($journey->deliveries()->where('status', 'pending')->exists()) {
                throw new OperationalApiException(
                    'يجب تسجيل نتيجة جميع التسليمات قبل إنهاء الرحلة.',
                    'pending_deliveries_exist',
                    409,
                );
            }

            $endOdometer = round((float) $payload['end_odometer'], 2);
            $startOdometer = round((float) $journey->start_odometer, 2);

            if ($endOdometer < $startOdometer) {
                throw new OperationalApiException(
                    'قراءة العداد عند نهاية الرحلة لا يمكن أن تكون أقل من قراءة البداية.',
                    'invalid_end_odometer',
                    422,
                );
            }

            $journey->forceFill([
                'status' => 'completed',
                'end_odometer' => $endOdometer,
                'finish_notes' => $payload['notes'] ?? null,
                'finished_at' => now(),
            ])->save();

            return $this->loadJourney($journey);
        });
    }

    public function loadJourney(DriverJourney $journey): DriverJourney
    {
        return $journey->refresh()->load([
            'route.area',
            'route.vehicle.warehouse',
            'route.driver',
            'route.salesRepresentative',
            'vehicle.warehouse',
            'warehouse.vehicle',
            'driver',
            'salesRepresentative',
            'deliveries.salesInvoice',
            'deliveries.customer',
            'deliveries.items.product.category',
            'deliveries.items.product.unit',
        ])->loadCount([
            'deliveries',
            'deliveries as pending_deliveries_count' => fn ($query) => $query->where('status', 'pending'),
            'deliveries as delivered_deliveries_count' => fn ($query) => $query->where('status', 'delivered'),
            'deliveries as partial_deliveries_count' => fn ($query) => $query->where('status', 'partial'),
            'deliveries as failed_deliveries_count' => fn ($query) => $query->where('status', 'failed'),
        ]);
    }

    public function loadDelivery(DriverDelivery $delivery): DriverDelivery
    {
        return $delivery->refresh()->load([
            'journey',
            'salesInvoice',
            'customer',
            'salesRepresentative',
            'items.product.category',
            'items.product.unit',
        ]);
    }

    public function generateJourneyNumber(): string
    {
        return app(DocumentNumberService::class)->next('driver_journey', 'JRN');
    }

    private function assertDeliveredOutcome(float $delivered, float $returned, float $expected): void
    {
        if ($returned > 0 || abs($delivered - $expected) > 0.0005) {
            throw new OperationalApiException(
                'نتيجة التسليم الكامل تحتاج تسليم كامل الكمية دون مرتجع.',
                'invalid_delivered_outcome',
                422,
            );
        }
    }

    private function assertPartialOutcome(float $delivered, float $returned): void
    {
        if ($delivered <= 0 || $returned <= 0) {
            throw new OperationalApiException(
                'التسليم الجزئي يحتاج كمية مسلمة وكمية مرتجعة أكبر من الصفر.',
                'invalid_partial_outcome',
                422,
            );
        }
    }

    private function assertFailedOutcome(float $delivered, float $returned, float $expected): void
    {
        if ($delivered > 0 || abs($returned - $expected) > 0.0005) {
            throw new OperationalApiException(
                'التسليم الفاشل يحتاج إرجاع كامل الكمية دون تسليم.',
                'invalid_failed_outcome',
                422,
            );
        }
    }
}
