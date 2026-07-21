<?php

namespace App\Services\Api;

use App\Exceptions\Api\OperationalApiException;
use App\Http\Requests\Api\V1\Operational\VehicleExpenseRejectRequest;
use App\Http\Requests\Api\V1\Operational\VehicleLoadHandoverRequest;
use App\Models\MobileSyncPushOperation;
use App\Models\User;
use App\Services\Distribution\DailyClosingService;
use App\Services\Distribution\VehicleExpenseService;
use App\Services\Distribution\VehicleLoadHandoverService;
use App\Services\Sales\CustomerPaymentService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesReturnService;
use App\Support\Api\MobileSyncEntityRegistry;
use App\Support\Api\MobileSyncPushRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MobileSyncPushOperationService
{
    public function __construct(
        private readonly MobileSyncPushRequestValidator $requestValidator,
        private readonly MobileOperationalWriteService $writeService,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly CustomerPaymentService $customerPaymentService,
        private readonly SalesReturnService $salesReturnService,
        private readonly VehicleExpenseService $vehicleExpenseService,
        private readonly VehicleLoadHandoverService $vehicleLoadHandoverService,
        private readonly DailyClosingService $dailyClosingService,
        private readonly MobileSyncVersionService $versionService,
    ) {
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    public function process(
        User $user,
        Request $request,
        string $deviceId,
        string $batchId,
        array $operation,
    ): array {
        $operationId = (string) $operation['operation_id'];
        $requestHash = $this->hash($operation);
        $existing = $this->findExisting($user, $deviceId, $operationId);

        if ($existing !== null) {
            return $this->replayOrConflict($existing, $requestHash, $operation);
        }

        try {
            return DB::transaction(function () use (
                $user,
                $request,
                $deviceId,
                $batchId,
                $operation,
                $operationId,
                $requestHash,
            ): array {
                $claimed = MobileSyncPushOperation::query()->create([
                    'user_id' => (int) $user->getKey(),
                    'device_id' => $deviceId,
                    'batch_id' => $batchId,
                    'operation_id' => $operationId,
                    'request_hash' => $requestHash,
                    'entity' => (string) $operation['entity'],
                    'action' => (string) $operation['action'],
                    'status' => 'processing',
                    'record_id' => $operation['record_id'] ?? null,
                    'client_reference' => data_get($operation, 'payload.client_reference'),
                    'base_version' => $operation['base_version'] ?? null,
                ]);

                $result = $this->execute($user, $request, $operation);

                $claimed->forceFill([
                    'status' => (string) $result['status'],
                    'http_status' => (int) $result['http_status'],
                    'record_id' => $result['record_id'] ?? $claimed->record_id,
                    'client_reference' => $result['client_reference'] ?? $claimed->client_reference,
                    'response_payload' => $result,
                    'processed_at' => now(),
                ])->save();

                return $result;
            });
        } catch (QueryException $exception) {
            $existing = $this->findExisting($user, $deviceId, $operationId);

            if ($existing === null) {
                throw $exception;
            }

            return $this->replayOrConflict($existing, $requestHash, $operation);
        }
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function execute(User $user, Request $request, array $operation): array
    {
        try {
            $entity = (string) $operation['entity'];
            $action = (string) $operation['action'];
            $payload = (array) ($operation['payload'] ?? []);

            if ($entity === 'vehicle_expenses'
                && (array_key_exists('receipt', $payload) || array_key_exists('remove_receipt', $payload))) {
                throw ValidationException::withMessages([
                    'receipt' => ['تغييرات صورة الإيصال غير مدعومة داخل push batch. استخدم مسار REST المباشر للرفع أو الحذف.'],
                ]);
            }

            if ($action === 'create') {
                return $this->create($user, $request, $entity, $payload, $operation);
            }

            $record = $this->findRecord($entity, (int) $operation['record_id']);

            return match ($action) {
                'update' => $this->update($user, $request, $entity, $record, $payload, $operation),
                'delete' => $this->delete($user, $request, $entity, $record, $operation),
                default => $this->action($user, $request, $entity, $action, $record, $payload, $operation),
            };
        } catch (ValidationException $exception) {
            return $this->failure($operation, 'failed', 422, 'validation_failed', 'البيانات المرسلة غير صالحة.', $exception->errors());
        } catch (AuthorizationException) {
            return $this->failure($operation, 'failed', 403, 'http_403', 'This action is unauthorized.');
        } catch (ModelNotFoundException) {
            return $this->failure($operation, 'failed', 404, 'http_404', 'السجل المطلوب غير موجود أو خارج نطاق الوصول.');
        } catch (OperationalApiException $exception) {
            return $this->failure(
                $operation,
                $exception->status === 409 ? 'conflict' : 'failed',
                $exception->status,
                $exception->apiCode,
                $exception->getMessage(),
                $exception->errors,
            );
        } catch (RuntimeException $exception) {
            if ($exception::class !== RuntimeException::class) {
                throw $exception;
            }

            return $this->failure($operation, 'conflict', 409, 'business_rule_violation', $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function create(
        User $user,
        Request $request,
        string $entity,
        array $payload,
        array $operation,
    ): array {
        $definition = MobileSyncPushRegistry::definition($entity);
        $formRequest = $this->requestValidator->make(
            $definition['request'],
            'POST',
            $payload,
            $user,
        );
        $this->requestValidator->authorize($formRequest);
        $validated = $this->requestValidator->validate($formRequest);

        $writeResult = match ($entity) {
            'sales_invoices' => $this->writeService->createSalesInvoice($validated),
            'customer_payments' => $this->writeService->createCustomerPayment($validated),
            'sales_returns' => $this->writeService->createSalesReturn($validated),
            'vehicle_expenses' => $this->writeService->createVehicleExpense($validated),
            'daily_closings' => $this->writeService->createDailyClosing($validated),
        };

        Gate::forUser($user)->authorize('view', $writeResult->record);

        return $this->success(
            $operation,
            $request,
            $entity,
            $writeResult->record,
            $writeResult->replayed ? 'replayed' : 'applied',
            $writeResult->replayed ? 200 : 201,
            $writeResult->replayed ? 'idempotent_replay' : 'created',
            $writeResult->replayed ? 'تمت إعادة السجل المنشأ سابقاً.' : 'تم إنشاء السجل.',
            $writeResult->replayed,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function update(
        User $user,
        Request $request,
        string $entity,
        Model $record,
        array $payload,
        array $operation,
    ): array {
        $definition = MobileSyncPushRegistry::definition($entity);
        $formRequest = $this->requestValidator->make(
            $definition['request'],
            'PATCH',
            $payload,
            $user,
            [$definition['route_parameter'] => $record],
        );
        Gate::forUser($user)->authorize('view', $record);
        $this->ensureVersion($request, $entity, $record, (string) $operation['base_version']);
        $this->requestValidator->authorize($formRequest);
        $validated = $this->requestValidator->validate($formRequest);

        $updated = match ($entity) {
            'sales_invoices' => $this->writeService->updateSalesInvoice($record, $validated),
            'customer_payments' => $this->writeService->updateCustomerPayment($record, $validated),
            'sales_returns' => $this->writeService->updateSalesReturn($record, $validated),
            'vehicle_expenses' => $this->writeService->updateVehicleExpense($record, $validated),
            'daily_closings' => $this->writeService->updateDailyClosing($record, $validated),
        };

        return $this->success($operation, $request, $entity, $updated, 'applied', 200, 'updated', 'تم تحديث السجل.');
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function delete(
        User $user,
        Request $request,
        string $entity,
        Model $record,
        array $operation,
    ): array {
        Gate::forUser($user)->authorize('view', $record);
        $this->ensureVersion($request, $entity, $record, (string) $operation['base_version']);
        Gate::forUser($user)->authorize('delete', $record);
        $recordId = (int) $record->getKey();
        $clientReference = $record->getAttribute('client_reference');
        $this->writeService->deleteRecord($record);

        return [
            'operation_id' => (string) $operation['operation_id'],
            'entity' => $entity,
            'action' => 'delete',
            'status' => 'applied',
            'replayed' => false,
            'http_status' => 200,
            'code' => 'deleted',
            'message' => 'تم حذف السجل.',
            'record_id' => $recordId,
            'client_reference' => $clientReference,
            'version' => null,
            'record' => null,
            'errors' => null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function action(
        User $user,
        Request $request,
        string $entity,
        string $action,
        Model $record,
        array $payload,
        array $operation,
    ): array {
        if ($entity === 'vehicle_expenses' && $action === 'reject') {
            $formRequest = $this->requestValidator->make(
                VehicleExpenseRejectRequest::class,
                'POST',
                $payload,
                $user,
                ['vehicleExpense' => $record],
            );
            Gate::forUser($user)->authorize('view', $record);
            $this->ensureVersion($request, $entity, $record, (string) $operation['base_version']);
            $this->requestValidator->authorize($formRequest);
            $payload = $this->requestValidator->validate($formRequest);
        } elseif ($entity === 'vehicle_loads' && $action === 'acknowledge') {
            $formRequest = $this->requestValidator->make(
                VehicleLoadHandoverRequest::class,
                'POST',
                $payload,
                $user,
                ['vehicleLoad' => $record],
            );
            Gate::forUser($user)->authorize('view', $record);
            $this->ensureVersion($request, $entity, $record, (string) $operation['base_version']);
            $this->requestValidator->authorize($formRequest);
            $payload = $this->requestValidator->validate($formRequest);
        } else {
            Gate::forUser($user)->authorize('view', $record);
            $this->ensureVersion($request, $entity, $record, (string) $operation['base_version']);
            Gate::forUser($user)->authorize($this->policyAction($action), $record);
        }

        $updated = match ([$entity, $action]) {
            ['sales_invoices', 'confirm'] => $this->salesInvoiceService->confirm($record),
            ['sales_invoices', 'cancel'] => $this->salesInvoiceService->cancel($record),
            ['customer_payments', 'confirm'] => $this->customerPaymentService->confirm($record),
            ['customer_payments', 'cancel'] => $this->customerPaymentService->cancel($record),
            ['sales_returns', 'confirm'] => $this->salesReturnService->confirm($record),
            ['sales_returns', 'cancel'] => $this->salesReturnService->cancel($record),
            ['vehicle_loads', 'acknowledge'] => $this->vehicleLoadHandoverService->acknowledge($record, $payload),
            ['vehicle_expenses', 'approve'] => $this->vehicleExpenseService->approve($record),
            ['vehicle_expenses', 'reject'] => $this->vehicleExpenseService->reject($record, $payload['reason']),
            ['daily_closings', 'refresh_totals'] => $this->dailyClosingService->refreshTotals($record),
            ['daily_closings', 'confirm'] => $this->dailyClosingService->confirm($record),
            ['daily_closings', 'cancel'] => $this->dailyClosingService->cancel($record),
        };

        return $this->success($operation, $request, $entity, $updated, 'applied', 200, $action, 'تم تنفيذ العملية.');
    }

    private function findRecord(string $entity, int $recordId): Model
    {
        $modelClass = MobileSyncPushRegistry::definition($entity)['model'];
        $record = $modelClass::query()->find($recordId);

        if (! $record instanceof Model) {
            throw (new ModelNotFoundException())->setModel($modelClass, [$recordId]);
        }

        return $record;
    }

    private function ensureVersion(
        Request $request,
        string $entity,
        Model $record,
        string $baseVersion,
    ): void {
        $currentVersion = $this->versionService->forRecord($entity, $record);

        if ($this->versionService->matches($baseVersion, $currentVersion)) {
            return;
        }

        throw new OperationalApiException(
            'تم تعديل السجل على الخادم بعد آخر مزامنة. اسحب النسخة الحالية ثم أعد تطبيق التغيير.',
            'sync_version_conflict',
            409,
            [
                'conflict' => [
                    'entity' => $entity,
                    'record_id' => (int) $record->getKey(),
                    'base_version' => $baseVersion,
                    'current_version' => $currentVersion,
                    'resolution' => 'server_wins_pull_then_retry',
                    'server_record' => $this->serializeRecord($entity, $record, $request)['record'],
                ],
            ],
        );
    }

    private function policyAction(string $action): string
    {
        return $action === 'refresh_totals' ? 'refreshTotals' : $action;
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function success(
        array $operation,
        Request $request,
        string $entity,
        Model $record,
        string $status,
        int $httpStatus,
        string $code,
        string $message,
        bool $idempotencyReplayed = false,
    ): array {
        $serialized = $this->serializeRecord($entity, $record, $request);

        return [
            'operation_id' => (string) $operation['operation_id'],
            'entity' => $entity,
            'action' => (string) $operation['action'],
            'status' => $status,
            'replayed' => false,
            'idempotency_replayed' => $idempotencyReplayed,
            'http_status' => $httpStatus,
            'code' => $code,
            'message' => $message,
            'record_id' => (int) $record->getKey(),
            'client_reference' => $record->getAttribute('client_reference'),
            'version' => $serialized['version'],
            'record' => $serialized['record'],
            'errors' => null,
        ];
    }

    /** @return array{version: ?string, record: array<string, mixed>} */
    private function serializeRecord(string $entity, Model $record, Request $request): array
    {
        $definition = MobileSyncEntityRegistry::definition($entity);
        $record->loadMissing($definition['relations']);
        $resourceClass = $definition['resource'];

        return [
            'version' => $this->versionService->forRecord($entity, $record),
            'record' => $resourceClass::make($record)->resolve($request),
        ];
    }


    /**
     * @param array<string, mixed> $operation
     * @param array<string, mixed>|null $errors
     * @return array<string, mixed>
     */
    private function failure(
        array $operation,
        string $status,
        int $httpStatus,
        string $code,
        string $message,
        ?array $errors = null,
    ): array {
        return [
            'operation_id' => (string) $operation['operation_id'],
            'entity' => (string) $operation['entity'],
            'action' => (string) $operation['action'],
            'status' => $status,
            'replayed' => false,
            'http_status' => $httpStatus,
            'code' => $code,
            'message' => $message,
            'record_id' => isset($operation['record_id']) ? (int) $operation['record_id'] : null,
            'client_reference' => data_get($operation, 'payload.client_reference'),
            'version' => null,
            'record' => null,
            'errors' => $errors,
        ];
    }

    private function findExisting(User $user, string $deviceId, string $operationId): ?MobileSyncPushOperation
    {
        return MobileSyncPushOperation::query()
            ->where('user_id', $user->getKey())
            ->where('device_id', $deviceId)
            ->where('operation_id', $operationId)
            ->first();
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function replayOrConflict(
        MobileSyncPushOperation $existing,
        string $requestHash,
        array $operation,
    ): array {
        if (! hash_equals((string) $existing->request_hash, $requestHash)) {
            return $this->failure(
                $operation,
                'conflict',
                409,
                'operation_idempotency_conflict',
                'تم استخدام operation_id نفسه سابقاً لعملية مختلفة.',
            );
        }

        if ($existing->status === 'processing' || ! is_array($existing->response_payload)) {
            return $this->failure(
                $operation,
                'conflict',
                409,
                'sync_operation_in_progress',
                'العملية نفسها ما زالت قيد المعالجة. أعد المحاولة لاحقاً.',
            );
        }

        return [
            ...$existing->response_payload,
            'status' => 'replayed',
            'replayed' => true,
            'replay_source' => 'operation_id',
        ];
    }

    /** @param array<string, mixed> $value */
    private function hash(array $value): string
    {
        return hash('sha256', json_encode(
            $this->normalize($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
