<?php

namespace App\Services\Distribution;

use App\Enums\OperationSource;
use App\Exceptions\Api\OperationalApiException;
use App\Models\Customer;
use App\Models\DistributionRoute;
use App\Models\SalesJourney;
use App\Models\SalesVisit;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLoad;
use App\Services\Support\DocumentNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesFieldOperationService
{
    public function __construct(
        private readonly FieldRouteAssignmentResolver $routeResolver,
    ) {}

    public function openToday(User $user, ?int $routeId = null): SalesJourney
    {
        $resolution = $this->routeResolver->resolveRole(
            $user,
            User::ROLE_SALES_REPRESENTATIVE,
            $routeId,
            today(),
        );
        $route = $resolution['route'];

        if (! $route instanceof DistributionRoute) {
            throw new RuntimeException(match ($resolution['status']) {
                FieldRouteAssignmentResolver::STATUS_ROUTE_SELECTION_REQUIRED => 'يوجد أكثر من خط مجدول اليوم. يجب تحديد خط المبيعات.',
                FieldRouteAssignmentResolver::STATUS_NOT_SCHEDULED_TODAY => 'لا يوجد خط مبيعات مجدول للمندوب في يوم العمل الحالي.',
                default => 'لا يوجد خط مبيعات فعّال مخصص لهذا المندوب.',
            });
        }

        if ($resolution['status'] !== FieldRouteAssignmentResolver::STATUS_READY) {
            throw new RuntimeException('خط المبيعات المحدد غير مجدول للمندوب اليوم.');
        }

        if ($route->sales_representative_id === null || $route->salesRepresentative?->status !== 'active') {
            throw new RuntimeException('خط المبيعات لا يحتوي مندوب مبيعات فعّالاً.');
        }

        $vehicle = $route->vehicle;
        $warehouse = $vehicle?->warehouse;

        if ($vehicle === null || $vehicle->status !== 'active') {
            throw new RuntimeException('خط المبيعات لا يحتوي سيارة فعّالة.');
        }

        if ($warehouse === null || $warehouse->type !== 'vehicle' || $warehouse->status !== 'active') {
            throw new RuntimeException('خط المبيعات لا يحتوي مستودع سيارة فعّالاً.');
        }

        return DB::transaction(function () use ($route, $vehicle, $warehouse): SalesJourney {
            $created = false;
            $journey = SalesJourney::withoutGlobalScopes()
                ->whereDate('journey_date', today())
                ->where('route_id', $route->getKey())
                ->where('sales_representative_id', $route->sales_representative_id)
                ->lockForUpdate()
                ->first();

            if ($journey === null) {
                $journey = SalesJourney::query()->create([
                    'journey_date' => today()->toDateString(),
                    'route_id' => $route->getKey(),
                    'vehicle_id' => $vehicle->getKey(),
                    'warehouse_id' => $warehouse->getKey(),
                    'sales_representative_id' => $route->sales_representative_id,
                    'status' => 'ready',
                    'created_by' => Auth::id(),
                    'operation_source' => OperationSource::MOBILE_SALES,
                ]);
                $created = true;
            }

            if ($created) {
                $this->syncRouteCustomers($journey);
            }

            $journey = $this->loadJourney($journey);
            $journey->wasRecentlyCreated = $created;

            return $journey;
        });
    }

    public function start(SalesJourney $journey, array $payload): SalesJourney
    {
        return DB::transaction(function () use ($journey, $payload): SalesJourney {
            $journey = SalesJourney::query()->lockForUpdate()->findOrFail($journey->getKey());

            if (! $journey->isReady()) {
                throw new RuntimeException('لا يمكن بدء رحلة مبيعات ليست بحالة جاهزة.');
            }

            $this->ensureActiveRepresentativeContext($journey);

            app(DailyClosingGuard::class)->ensureOpen(
                $journey->journey_date,
                (int) $journey->warehouse_id,
            );

            $conflictingJourneyExists = SalesJourney::withoutGlobalScopes()
                ->whereDate('journey_date', $journey->journey_date)
                ->where($journey->getQualifiedKeyName(), '!=', $journey->getKey())
                ->where('status', 'in_progress')
                ->where(function ($query) use ($journey): void {
                    $query->where('sales_representative_id', $journey->sales_representative_id)
                        ->orWhere('vehicle_id', $journey->vehicle_id)
                        ->orWhere('warehouse_id', $journey->warehouse_id);
                })
                ->exists();

            if ($conflictingJourneyExists) {
                throw new OperationalApiException(
                    'توجد رحلة ميدانية أخرى قيد التنفيذ للمندوب أو السيارة المحددة.',
                    'sales_journey_conflict',
                    409,
                );
            }

            $approvedLoads = VehicleLoad::withoutGlobalScopes()
                ->whereDate('load_date', $journey->journey_date)
                ->where('route_id', $journey->route_id)
                ->where('vehicle_id', $journey->vehicle_id)
                ->where('to_warehouse_id', $journey->warehouse_id)
                ->where('status', 'approved')
                ->where(function ($query) use ($journey): void {
                    $query->where('sales_representative_id', $journey->sales_representative_id)
                        ->orWhereNull('sales_representative_id');
                })
                ->lockForUpdate()
                ->get(['id', 'handover_status']);

            if ($approvedLoads->isEmpty()) {
                throw new OperationalApiException(
                    'لا يمكن بدء رحلة اليوم قبل تجهيز حمولة معتمدة ليوم العمل الحالي واستلامها.',
                    'vehicle_load_required',
                    409,
                );
            }

            $pendingLoadExists = $approvedLoads->contains(
                fn (VehicleLoad $load): bool => ! in_array(
                    $load->handover_status,
                    ['received', 'discrepancy'],
                    true,
                ),
            );

            if ($pendingLoadExists) {
                throw new OperationalApiException(
                    'يجب استلام جميع أوامر التحميل المعتمدة قبل بدء الرحلة.',
                    'vehicle_load_handover_pending',
                    409,
                );
            }

            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail((int) $journey->vehicle_id);
            $startOdometer = (int) $payload['start_odometer'];
            $this->ensureOdometerDoesNotRegress(
                $startOdometer,
                $vehicle->current_odometer === null ? null : (int) $vehicle->current_odometer,
                'start_odometer',
            );

            // The visit plan is frozen when the journey is first opened.
            // Customers created afterwards are attached only when the caller
            // explicitly requests attach_to_today_journey.
            $journey->forceFill([
                'status' => 'in_progress',
                'start_odometer' => $startOdometer,
                'end_odometer' => null,
                'distance_km' => null,
                'start_notes' => $payload['notes'] ?? null,
                'started_at' => now(),
            ])->save();

            if ($vehicle->current_odometer === null || $startOdometer > (int) $vehicle->current_odometer) {
                $vehicle->forceFill(['current_odometer' => $startOdometer])->save();
            }

            return $this->loadJourney($journey);
        });
    }

    public function startVisit(SalesVisit $visit, array $payload): SalesVisit
    {
        return DB::transaction(function () use ($visit, $payload): SalesVisit {
            $visit = SalesVisit::query()->with('journey')->lockForUpdate()->findOrFail($visit->getKey());

            if (! $visit->journey?->isInProgress()) {
                throw new RuntimeException('يجب بدء رحلة المبيعات قبل بدء الزيارة.');
            }

            if (! $visit->isPending()) {
                throw new RuntimeException('لا يمكن بدء زيارة ليست بحالة معلقة.');
            }

            $otherActive = SalesVisit::withoutGlobalScopes()
                ->where('sales_journey_id', $visit->sales_journey_id)
                ->where('status', 'in_progress')
                ->where('id', '!=', $visit->getKey())
                ->exists();

            if ($otherActive) {
                throw new OperationalApiException(
                    'توجد زيارة أخرى قيد التنفيذ. يجب إنهاؤها أولاً.',
                    'sales_visit_already_in_progress',
                    409,
                );
            }

            app(DailyClosingGuard::class)->ensureOpen(
                $visit->journey->journey_date,
                (int) $visit->warehouse_id,
            );

            $visit->forceFill([
                'status' => 'in_progress',
                'started_at' => now(),
                'start_latitude' => $payload['latitude'] ?? null,
                'start_longitude' => $payload['longitude'] ?? null,
                'start_notes' => $payload['notes'] ?? null,
                'started_by' => Auth::id(),
            ])->save();
            $visit->journey->touch();

            return $this->loadVisit($visit);
        });
    }

    public function completeVisit(SalesVisit $visit, array $payload): SalesVisit
    {
        return DB::transaction(function () use ($visit, $payload): SalesVisit {
            $visit = SalesVisit::query()->with('journey')->lockForUpdate()->findOrFail($visit->getKey());

            if (! $visit->journey?->isInProgress()) {
                throw new RuntimeException('لا يمكن إنهاء زيارة خارج رحلة مبيعات قيد التنفيذ.');
            }

            if (! $visit->isInProgress()) {
                throw new RuntimeException('لا يمكن إنهاء زيارة لم تبدأ أو انتهت مسبقاً.');
            }

            app(DailyClosingGuard::class)->ensureOpen(
                $visit->journey->journey_date,
                (int) $visit->warehouse_id,
            );

            $this->ensureOutcomeMatchesDocuments($visit, (string) $payload['outcome']);

            $visit->forceFill([
                'status' => 'completed',
                'outcome' => $payload['outcome'],
                'completed_at' => now(),
                'completion_latitude' => $payload['latitude'] ?? null,
                'completion_longitude' => $payload['longitude'] ?? null,
                'completion_notes' => $payload['notes'] ?? null,
                'completed_by' => Auth::id(),
            ])->save();
            $visit->journey->touch();

            return $this->loadVisit($visit);
        });
    }

    public function finish(SalesJourney $journey, array $payload): SalesJourney
    {
        return DB::transaction(function () use ($journey, $payload): SalesJourney {
            $journey = SalesJourney::query()->lockForUpdate()->findOrFail($journey->getKey());

            if (! $journey->isInProgress()) {
                throw new RuntimeException('لا يمكن إنهاء رحلة مبيعات ليست قيد التنفيذ.');
            }

            $this->ensureActiveRepresentativeContext($journey);

            app(DailyClosingGuard::class)->ensureOpen(
                $journey->journey_date,
                (int) $journey->warehouse_id,
            );

            // The visit plan is frozen once the journey starts. Customers
            // created during an active journey are attached only when the
            // caller explicitly requests it. Re-syncing the whole route here
            // would silently add pending visits at finish time and make an
            // otherwise completed offline journey conflict.
            if ($journey->visits()->where('status', '!=', 'completed')->exists()) {
                throw new OperationalApiException(
                    'لا يمكن إنهاء الرحلة قبل معالجة جميع الزيارات المخططة.',
                    'sales_visits_pending',
                    409,
                );
            }

            if ($journey->start_odometer === null) {
                throw new OperationalApiException(
                    'لا يمكن إنهاء الرحلة قبل تسجيل قراءة عداد البداية.',
                    'sales_journey_start_odometer_missing',
                    409,
                );
            }

            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail((int) $journey->vehicle_id);
            $startOdometer = (int) $journey->start_odometer;
            $endOdometer = (int) $payload['end_odometer'];

            $this->ensureOdometerDoesNotRegress($endOdometer, $startOdometer, 'end_odometer');
            $this->ensureOdometerDoesNotRegress(
                $endOdometer,
                $vehicle->current_odometer === null ? null : (int) $vehicle->current_odometer,
                'end_odometer',
            );

            $journey->forceFill([
                'status' => 'completed',
                'finished_at' => now(),
                'end_odometer' => $endOdometer,
                'distance_km' => $endOdometer - $startOdometer,
                'finish_notes' => $payload['notes'] ?? null,
            ])->save();

            if ($vehicle->current_odometer === null || $endOdometer > (int) $vehicle->current_odometer) {
                $vehicle->forceFill(['current_odometer' => $endOdometer])->save();
            }

            return $this->loadJourney($journey);
        });
    }

    public function attachNewCustomer(Customer $customer): void
    {
        $journey = SalesJourney::withoutGlobalScopes()
            ->whereDate('journey_date', today())
            ->where('route_id', $customer->route_id)
            ->where('status', '!=', 'completed')
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if ($journey !== null) {
            $this->createVisitForCustomer($journey, $customer);
            $journey->touch();
        }
    }

    public function assertDocumentVisitContext(Model $document): void
    {
        $visitId = $document->getAttribute('sales_visit_id');
        if ($visitId === null) {
            return;
        }

        $visit = SalesVisit::withoutGlobalScopes()->with('journey')->find((int) $visitId);
        if ($visit === null) {
            throw new OperationalApiException('الزيارة المحددة غير موجودة.', 'sales_visit_not_found', 422);
        }

        $matches = (int) $document->getAttribute('customer_id') === (int) $visit->customer_id
            && (int) $document->getAttribute('route_id') === (int) $visit->route_id
            && (int) $document->getAttribute('sales_representative_id') === (int) $visit->sales_representative_id
            && (int) $document->getAttribute('warehouse_id') === (int) $visit->warehouse_id;

        if (! $matches) {
            throw new OperationalApiException(
                'بيانات المستند لا تطابق العميل والسياق التشغيلي للزيارة.',
                'sales_visit_context_mismatch',
                422,
            );
        }

        $documentDate = collect(['invoice_date', 'payment_date', 'return_date'])
            ->map(fn (string $field): mixed => $document->getAttribute($field))
            ->first(fn (mixed $value): bool => filled($value));
        $journeyDate = $visit->journey?->journey_date;

        if (
            $documentDate !== null
            && $journeyDate !== null
            && Carbon::parse($documentDate)->toDateString() !== $journeyDate->toDateString()
        ) {
            throw new OperationalApiException(
                'تاريخ المستند يجب أن يطابق تاريخ رحلة الزيارة.',
                'sales_visit_document_date_mismatch',
                422,
            );
        }

        if (! $visit->journey?->isInProgress() || ! $visit->isInProgress()) {
            throw new OperationalApiException(
                'يجب أن تكون الزيارة ورحلتها قيد التنفيذ لربط مستند ميداني بها.',
                'sales_visit_not_active',
                409,
            );
        }
    }

    private function ensureOutcomeMatchesDocuments(SalesVisit $visit, string $outcome): void
    {
        $documentTypes = collect([
            'invoice_created' => $visit->invoices()->exists(),
            'collection_recorded' => $visit->payments()->exists(),
            'return_recorded' => $visit->returns()->exists(),
        ]);

        if (isset($documentTypes[$outcome]) && ! $documentTypes[$outcome]) {
            throw new OperationalApiException(
                'نتيجة الزيارة المحددة تحتاج مستنداً ميدانياً مرتبطاً بالزيارة.',
                'sales_visit_outcome_document_missing',
                422,
                ['outcome' => ['لا يوجد مستند رسمي مطابق لنتيجة الزيارة.']],
            );
        }

        if ($outcome === 'mixed' && $documentTypes->filter()->count() < 2) {
            throw new OperationalApiException(
                'النتيجة المختلطة تحتاج نوعين مختلفين على الأقل من المستندات المرتبطة بالزيارة.',
                'sales_visit_mixed_outcome_incomplete',
                422,
                ['outcome' => ['أضف نوعين مختلفين على الأقل من الفاتورة أو التحصيل أو المرتجع.']],
            );
        }
    }

    public function touchVisitForDocument(Model $document): void
    {
        $visitId = $document->getAttribute('sales_visit_id');
        if ($visitId === null) {
            return;
        }

        $visit = SalesVisit::withoutGlobalScopes()->with('journey')->find((int) $visitId);
        $visit?->touch();
        $visit?->journey?->touch();
    }

    public function loadJourney(SalesJourney $journey): SalesJourney
    {
        $journey = $journey->refresh()->load($this->journeyRelations())->loadCount([
            'visits',
            'visits as pending_visits_count' => fn ($query) => $query->where('status', 'pending'),
            'visits as in_progress_visits_count' => fn ($query) => $query->where('status', 'in_progress'),
            'visits as completed_visits_count' => fn ($query) => $query->where('status', 'completed'),
        ]);
        $journey->visits->loadCount(['invoices', 'payments', 'returns']);

        return $journey;
    }

    public function loadVisit(SalesVisit $visit): SalesVisit
    {
        return $visit->refresh()
            ->load($this->visitRelations())
            ->loadCount(['invoices', 'payments', 'returns']);
    }

    public function generateJourneyNumber(): string
    {
        return app(DocumentNumberService::class)->next('sales_journey', 'SJ');
    }

    private function ensureOdometerDoesNotRegress(int $reading, ?int $baseline, string $field): void
    {
        if ($baseline === null || $reading >= $baseline) {
            return;
        }

        throw new OperationalApiException(
            'قراءة عداد المركبة لا يمكن أن تكون أقل من آخر قراءة موثوقة.',
            'vehicle_odometer_regression',
            422,
            [$field => ['يجب أن تكون قراءة العداد مساوية أو أكبر من آخر قراءة موثوقة.']],
        );
    }

    private function ensureActiveRepresentativeContext(SalesJourney $journey): void
    {
        $user = Auth::user();
        $employeeId = $user instanceof User
            ? $user->employee()->value('id')
            : null;

        $journey->loadMissing([
            'route.salesRepresentative',
            'route.vehicle.warehouse',
        ]);
        $route = $journey->route;
        $vehicle = $route?->vehicle;
        $warehouse = $vehicle?->warehouse;

        $valid = $user instanceof User
            && $user->hasRole(User::ROLE_SALES_REPRESENTATIVE)
            && $employeeId !== null
            && (int) $employeeId === (int) $journey->sales_representative_id
            && $journey->journey_date?->isToday()
            && $route?->status === 'active'
            && (int) $route?->sales_representative_id === (int) $journey->sales_representative_id
            && $route?->salesRepresentative?->status === 'active'
            && $vehicle?->status === 'active'
            && (int) $vehicle?->getKey() === (int) $journey->vehicle_id
            && $warehouse?->type === 'vehicle'
            && $warehouse?->status === 'active'
            && (int) $warehouse?->getKey() === (int) $journey->warehouse_id;

        if (! $valid) {
            throw new OperationalApiException(
                'سياق رحلة المندوب لم يعد يطابق التعيين التشغيلي الفعال لليوم.',
                'sales_journey_context_mismatch',
                409,
            );
        }
    }

    private function syncRouteCustomers(SalesJourney $journey): void
    {
        $customers = Customer::withoutGlobalScopes()
            ->where('route_id', $journey->route_id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        foreach ($customers as $customer) {
            $this->createVisitForCustomer($journey, $customer);
        }
    }

    private function createVisitForCustomer(SalesJourney $journey, Customer $customer): SalesVisit
    {
        $existing = SalesVisit::withoutGlobalScopes()
            ->where('sales_journey_id', $journey->getKey())
            ->where('customer_id', $customer->getKey())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $sequence = ((int) SalesVisit::withoutGlobalScopes()
            ->where('sales_journey_id', $journey->getKey())
            ->max('planned_sequence')) + 1;

        return SalesVisit::query()->create([
            'sales_journey_id' => $journey->getKey(),
            'customer_id' => $customer->getKey(),
            'route_id' => $journey->route_id,
            'area_id' => $customer->area_id,
            'vehicle_id' => $journey->vehicle_id,
            'warehouse_id' => $journey->warehouse_id,
            'sales_representative_id' => $journey->sales_representative_id,
            'planned_sequence' => $sequence,
            'status' => 'pending',
        ]);
    }

    /** @return list<string> */
    private function journeyRelations(): array
    {
        return [
            'route.area', 'route.vehicle.warehouse', 'vehicle.warehouse',
            'warehouse.vehicle', 'salesRepresentative',
            'visits.customer.area', 'visits.customer.route',
        ];
    }

    /** @return list<string> */
    private function visitRelations(): array
    {
        return [
            'journey', 'customer.area', 'customer.route', 'route.area',
            'vehicle.warehouse', 'warehouse.vehicle', 'salesRepresentative',
        ];
    }
}
