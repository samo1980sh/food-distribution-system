<?php

namespace App\Services\Api;

use App\Enums\OperationSource;
use App\Exceptions\Api\OperationalApiException;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\VehicleExpense;
use App\Services\Distribution\DailyClosingService;
use App\Services\Distribution\FieldRouteAssignmentResolver;
use App\Services\Distribution\SalesFieldOperationService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesReturnService;
use App\Services\Support\DocumentNumberService;
use App\Services\Support\VehicleExpenseReceiptService;
use App\Support\Api\MobileWriteResult;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class MobileOperationalWriteService
{
    public function __construct(
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly SalesReturnService $salesReturnService,
        private readonly DailyClosingService $dailyClosingService,
        private readonly SalesFieldOperationService $salesFieldOperationService,
        private readonly FieldRouteAssignmentResolver $fieldRouteAssignmentResolver,
        private readonly DocumentNumberService $documentNumberService,
        private readonly VehicleExpenseReceiptService $vehicleExpenseReceipts,
    ) {}

    public function createCustomer(array $data): MobileWriteResult
    {
        return $this->idempotentCreate(
            Customer::class,
            $data,
            function (string $payloadHash) use ($data): Customer {
                $route = DistributionRoute::query()
                    ->with(['area', 'salesRepresentative'])
                    ->where('status', 'active')
                    ->findOrFail((int) $data['route_id']);

                if ($route->sales_representative_id === null) {
                    throw new OperationalApiException(
                        'خط التوزيع لا يحتوي مندوب مبيعات فعّالاً.',
                        'sales_route_representative_missing',
                        422,
                    );
                }

                $attachToTodayJourney = (bool) ($data['attach_to_today_journey'] ?? false);
                unset($data['attach_to_today_journey']);

                $customer = Customer::query()->create([
                    ...$data,
                    'code' => $this->documentNumberService->next('customer', 'CUS'),
                    'area_id' => $route->area_id,
                    'customer_type' => $data['customer_type'] ?? 'grocery',
                    'credit_limit' => $data['credit_limit'] ?? 0,
                    'credit_days' => $data['credit_days'] ?? 30,
                    'payment_type' => $data['payment_type'] ?? 'cash',
                    'status' => 'active',
                    'created_by' => Auth::id(),
                    'client_payload_hash' => $payloadHash,
                    'operation_source' => OperationSource::MOBILE_SALES,
                ]);

                if ($attachToTodayJourney) {
                    $this->salesFieldOperationService->attachNewCustomer($customer);
                }

                return $customer->refresh();
            },
        );
    }

    public function createSalesInvoice(array $data): MobileWriteResult
    {
        // AUTO_CONFIRM_R1: field invoice create + confirm is one atomic server transaction.
        return DB::transaction(function () use ($data): MobileWriteResult {
            $items = Arr::pull($data, 'items', []);

            return $this->idempotentCreate(
                SalesInvoice::class,
                [...$data, 'items' => $items],
                function (string $payloadHash) use ($data, $items): SalesInvoice {
                    $invoice = new SalesInvoice([
                        ...$data,
                        'created_by' => Auth::id(),
                        'client_payload_hash' => $payloadHash,
                        'operation_source' => OperationSource::MOBILE_SALES,
                    ]);
                    $this->salesFieldOperationService->assertDocumentVisitContext($invoice);
                    $invoice->save();

                    $invoice->items()->createMany($items);
                    $this->salesInvoiceService->recalculateTotals($invoice);
                    $this->salesFieldOperationService->touchVisitForDocument($invoice);

                    return $this->salesInvoiceService->confirm($invoice->refresh());
                },
            );

        });
    }

    public function updateSalesInvoice(SalesInvoice $invoice, array $data): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $data): SalesInvoice {
            $hasItems = array_key_exists('items', $data);
            $items = Arr::pull($data, 'items', []);

            $invoice->fill($data);
            $this->salesFieldOperationService->assertDocumentVisitContext($invoice);
            $invoice->save();

            if ($hasItems) {
                $invoice->items()->delete();
                $invoice->items()->createMany($items);
            }

            $this->salesInvoiceService->recalculateTotals($invoice);
            $this->salesFieldOperationService->touchVisitForDocument($invoice);

            return $invoice->refresh();
        });
    }

    public function createCustomerPayment(array $data): MobileWriteResult
    {
        return $this->idempotentCreate(
            CustomerPayment::class,
            $data,
            function (string $payloadHash) use ($data): CustomerPayment {
                $payment = new CustomerPayment([
                    ...$data,
                    'created_by' => Auth::id(),
                    'client_payload_hash' => $payloadHash,
                    'operation_source' => OperationSource::MOBILE_SALES,
                ]);
                $this->salesFieldOperationService->assertDocumentVisitContext($payment);
                $payment->save();
                $this->salesFieldOperationService->touchVisitForDocument($payment);

                return $payment;
            },
        );
    }

    public function updateCustomerPayment(CustomerPayment $payment, array $data): CustomerPayment
    {
        return DB::transaction(function () use ($payment, $data): CustomerPayment {
            $payment->fill($data);
            $this->salesFieldOperationService->assertDocumentVisitContext($payment);
            $payment->save();
            $this->salesFieldOperationService->touchVisitForDocument($payment);

            return $payment->refresh();
        });
    }

    public function createSalesReturn(array $data): MobileWriteResult
    {
        $items = Arr::pull($data, 'items', []);

        return $this->idempotentCreate(
            SalesReturn::class,
            [...$data, 'items' => $items],
            function (string $payloadHash) use ($data, $items): SalesReturn {
                $salesReturn = new SalesReturn([
                    ...$data,
                    'created_by' => Auth::id(),
                    'client_payload_hash' => $payloadHash,
                    'operation_source' => OperationSource::MOBILE_SALES,
                ]);
                $this->salesFieldOperationService->assertDocumentVisitContext($salesReturn);
                $salesReturn->save();

                $salesReturn->items()->createMany($items);
                $this->salesReturnService->recalculateTotals($salesReturn);
                $this->salesFieldOperationService->touchVisitForDocument($salesReturn);

                return $salesReturn->refresh();
            },
        );
    }

    public function updateSalesReturn(SalesReturn $salesReturn, array $data): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn, $data): SalesReturn {
            $hasItems = array_key_exists('items', $data);
            $items = Arr::pull($data, 'items', []);

            $salesReturn->fill($data);
            $this->salesFieldOperationService->assertDocumentVisitContext($salesReturn);
            $salesReturn->save();

            if ($hasItems) {
                $salesReturn->items()->delete();
                $salesReturn->items()->createMany($items);
            }

            $this->salesReturnService->recalculateTotals($salesReturn);
            $this->salesFieldOperationService->touchVisitForDocument($salesReturn);

            return $salesReturn->refresh();
        });
    }

    public function createVehicleExpense(
        array $data,
        ?UploadedFile $receipt = null,
    ): MobileWriteResult {
        unset($data['receipt'], $data['remove_receipt']);
        $data = $this->representativeExpenseData($data);

        return $this->idempotentCreate(
            VehicleExpense::class,
            $this->payloadWithFileFingerprint($data, $receipt),
            function (string $payloadHash) use ($data, $receipt): VehicleExpense {
                $receiptPath = $this->vehicleExpenseReceipts->store($receipt);

                try {
                    return VehicleExpense::query()->create([
                        ...$data,
                        'receipt_path' => $receiptPath,
                        'created_by' => Auth::id(),
                        'client_payload_hash' => $payloadHash,
                        'operation_source' => $this->vehicleExpenseOperationSource($data),
                    ]);
                } catch (Throwable $exception) {
                    if ($receiptPath) {
                        $this->vehicleExpenseReceipts->delete($receiptPath);
                    }

                    throw $exception;
                }
            },
        );
    }

    public function updateVehicleExpense(
        VehicleExpense $expense,
        array $data,
        ?UploadedFile $receipt = null,
    ): VehicleExpense {
        unset($data['receipt']);
        $data = $this->representativeExpenseUpdateData($expense, $data);
        $removeReceipt = filter_var(
            Arr::pull($data, 'remove_receipt', false),
            FILTER_VALIDATE_BOOLEAN,
        );
        $newReceiptPath = $this->vehicleExpenseReceipts->store($receipt);
        $oldReceiptPath = $expense->receipt_path;

        try {
            $updated = DB::transaction(function () use (
                $expense,
                $data,
                $newReceiptPath,
                $removeReceipt,
            ): VehicleExpense {
                if ($newReceiptPath !== null) {
                    $data['receipt_path'] = $newReceiptPath;
                } elseif ($removeReceipt) {
                    $data['receipt_path'] = null;
                }

                $expense->fill($data)->save();

                return $expense->refresh();
            });
        } catch (Throwable $exception) {
            if ($newReceiptPath) {
                $this->vehicleExpenseReceipts->delete($newReceiptPath);
            }

            throw $exception;
        }

        if (($newReceiptPath !== null || $removeReceipt) && $oldReceiptPath) {
            $this->vehicleExpenseReceipts->delete($oldReceiptPath);
        }

        return $updated;
    }

    public function createDailyClosing(array $data): MobileWriteResult
    {
        unset($data['items']);

        return $this->idempotentCreate(
            DailyClosing::class,
            $data,
            function (string $payloadHash) use ($data): DailyClosing {
                $closing = DailyClosing::query()->create([
                    ...$data,
                    'created_by' => Auth::id(),
                    'client_payload_hash' => $payloadHash,
                    'operation_source' => OperationSource::MOBILE_SALES,
                ]);

                return $this->dailyClosingService->refreshTotals($closing);
            },
        );
    }

    public function updateDailyClosing(DailyClosing $closing, array $data): DailyClosing
    {
        return DB::transaction(function () use ($closing, $data): DailyClosing {
            $items = Arr::pull($data, 'items', null);

            $closing->fill($data)->save();
            $closing = $this->dailyClosingService->refreshTotals($closing);

            if (is_array($items)) {
                foreach ($items as $itemData) {
                    $item = $closing->items()
                        ->where('product_id', $itemData['product_id'])
                        ->first();

                    if ($item === null) {
                        throw new OperationalApiException(
                            'لا يمكن إدخال جرد لمنتج غير موجود ضمن ملخص هذا الإغلاق.',
                            'closing_item_not_found',
                            422,
                        );
                    }

                    $item->fill(Arr::only($itemData, [
                        'actual_quantity',
                        'notes',
                    ]))->save();
                }
            }

            return $closing->refresh();
        });
    }

    public function deleteRecord(Model $record): void
    {
        $receiptPath = $record instanceof VehicleExpense
            ? $record->receipt_path
            : null;

        DB::transaction(function () use ($record): void {
            $record->delete();
        });

        if ($receiptPath) {
            $this->vehicleExpenseReceipts->delete($receiptPath);
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  array<string, mixed>  $payload
     * @param  Closure(string): TModel  $creator
     */
    private function idempotentCreate(
        string $modelClass,
        array $payload,
        Closure $creator,
    ): MobileWriteResult {
        $clientReference = (string) ($payload['client_reference'] ?? '');
        $payloadHash = $this->payloadHash($payload);
        $existing = $this->findExisting($modelClass, $clientReference);

        if ($existing !== null) {
            return $this->replayExisting($existing, $payloadHash);
        }

        try {
            $record = DB::transaction(
                fn (): Model => $creator($payloadHash),
            );

            return new MobileWriteResult($record, false);
        } catch (QueryException $exception) {
            $existing = $this->findExisting($modelClass, $clientReference);

            if ($existing === null) {
                throw $exception;
            }

            return $this->replayExisting($existing, $payloadHash);
        }
    }

    /** @param class-string<Model> $modelClass */
    private function findExisting(
        string $modelClass,
        string $clientReference,
    ): ?Model {
        if ($clientReference === '' || Auth::id() === null) {
            return null;
        }

        return $modelClass::withoutGlobalScopes()
            ->where('created_by', Auth::id())
            ->where('client_reference', $clientReference)
            ->first();
    }

    private function replayExisting(
        Model $existing,
        string $payloadHash,
    ): MobileWriteResult {
        if (! hash_equals(
            (string) $existing->getAttribute('client_payload_hash'),
            $payloadHash,
        )) {
            throw new OperationalApiException(
                'تم استخدام client_reference نفسه سابقاً مع بيانات مختلفة.',
                'idempotency_conflict',
                409,
            );
        }

        return new MobileWriteResult($existing, true);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function representativeExpenseData(array $data): array
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $this->usesRepresentativeExpenseOwnership($user, $data)) {
            return $data;
        }

        $employeeId = $user->employee()->value('id');

        if ($employeeId === null) {
            throw new OperationalApiException(
                'يجب ربط حساب المندوب بموظف فعال قبل تسجيل مصروف المركبة.',
                'field_employee_missing',
                422,
            );
        }

        $routeId = isset($data['route_id']) ? (int) $data['route_id'] : null;
        $resolution = $this->fieldRouteAssignmentResolver->resolveRole(
            $user,
            User::ROLE_SALES_REPRESENTATIVE,
            $routeId,
        );
        $route = $resolution['route'];

        if (
            ! $route instanceof DistributionRoute
            || (int) $route->sales_representative_id !== (int) $employeeId
        ) {
            throw new OperationalApiException(
                'يجب تحديد خط توزيع فعال مخصص للمندوب لتسجيل مصروف المركبة.',
                'field_route_assignment_required',
                422,
            );
        }

        $vehicle = $route->vehicle;
        $warehouse = $vehicle?->warehouse;

        if ($vehicle === null || $vehicle->status !== 'active') {
            throw new OperationalApiException(
                'خط التوزيع لا يرتبط بسيارة فعالة.',
                'field_vehicle_missing',
                422,
            );
        }

        if (
            $warehouse === null
            || $warehouse->type !== 'vehicle'
            || $warehouse->status !== 'active'
        ) {
            throw new OperationalApiException(
                'السيارة المخصصة للمندوب لا تملك مستودع سيارة فعالاً.',
                'field_vehicle_warehouse_missing',
                422,
            );
        }

        foreach ([
            'vehicle_id' => (int) $vehicle->getKey(),
            'warehouse_id' => (int) $warehouse->getKey(),
            'sales_representative_id' => (int) $employeeId,
        ] as $field => $expectedId) {
            if (
                array_key_exists($field, $data)
                && $data[$field] !== null
                && (int) $data[$field] !== $expectedId
            ) {
                throw new OperationalApiException(
                    'سياق مصروف المركبة لا يطابق خط وسيارة ومستودع المندوب.',
                    'field_context_mismatch',
                    422,
                );
            }
        }

        return [
            ...$data,
            'route_id' => (int) $route->getKey(),
            'vehicle_id' => (int) $vehicle->getKey(),
            'warehouse_id' => (int) $warehouse->getKey(),
            'sales_representative_id' => (int) $employeeId,
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function representativeExpenseUpdateData(
        VehicleExpense $expense,
        array $data,
    ): array {
        $user = Auth::user();
        $employeeId = $user instanceof User
            ? $user->employee()->value('id')
            : null;

        if (
            ! $user instanceof User
            || ! $user->hasRole(User::ROLE_SALES_REPRESENTATIVE)
            || $expense->operation_source !== OperationSource::MOBILE_SALES
            || $employeeId === null
            || (int) $expense->sales_representative_id !== (int) $employeeId
        ) {
            return $data;
        }

        $context = $this->representativeExpenseData([
            'route_id' => $data['route_id'] ?? $expense->route_id,
            'vehicle_id' => $data['vehicle_id'] ?? $expense->vehicle_id,
            'warehouse_id' => $data['warehouse_id'] ?? $expense->warehouse_id,
            'sales_representative_id' => $data['sales_representative_id']
                ?? $expense->sales_representative_id,
        ]);

        return [
            ...$data,
            ...Arr::only($context, [
                'route_id',
                'vehicle_id',
                'warehouse_id',
                'sales_representative_id',
            ]),
        ];
    }

    /** @param array<string, mixed> $data */
    private function usesRepresentativeExpenseOwnership(User $user, array $data): bool
    {
        if (! $user->hasRole(User::ROLE_SALES_REPRESENTATIVE)) {
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    private function vehicleExpenseOperationSource(array $data): OperationSource
    {
        $user = Auth::user();

        return OperationSource::MOBILE_SALES;
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        return hash(
            'sha256',
            json_encode(
                $this->normalizeForHash($payload),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
        );
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function payloadWithFileFingerprint(
        array $payload,
        ?UploadedFile $file,
    ): array {
        if ($file === null) {
            return $payload;
        }

        $payload['_receipt'] = [
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ];

        return $payload;
    }

    private function normalizeForHash(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->normalizeForHash($item),
                $value,
            );
        }

        ksort($value);

        return array_map(
            fn (mixed $item): mixed => $this->normalizeForHash($item),
            $value,
        );
    }
}
