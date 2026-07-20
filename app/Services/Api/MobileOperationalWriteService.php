<?php

namespace App\Services\Api;

use App\Enums\OperationSource;
use App\Exceptions\Api\OperationalApiException;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\VehicleExpense;
use App\Services\Distribution\DailyClosingService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesReturnService;
use App\Support\Api\MobileWriteResult;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MobileOperationalWriteService
{
    public function __construct(
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly SalesReturnService $salesReturnService,
        private readonly DailyClosingService $dailyClosingService,
    ) {
    }

    public function createSalesInvoice(array $data): MobileWriteResult
    {
        $items = Arr::pull($data, 'items', []);

        return $this->idempotentCreate(
            SalesInvoice::class,
            [...$data, 'items' => $items],
            function (string $payloadHash) use ($data, $items): SalesInvoice {
                $invoice = SalesInvoice::query()->create([
                    ...$data,
                    'created_by' => Auth::id(),
                    'client_payload_hash' => $payloadHash,
                    'operation_source' => OperationSource::MOBILE_SALES,
                ]);

                $invoice->items()->createMany($items);
                $this->salesInvoiceService->recalculateTotals($invoice);

                return $invoice->refresh();
            },
        );
    }

    public function updateSalesInvoice(SalesInvoice $invoice, array $data): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $data): SalesInvoice {
            $hasItems = array_key_exists('items', $data);
            $items = Arr::pull($data, 'items', []);

            $invoice->fill($data)->save();

            if ($hasItems) {
                $invoice->items()->delete();
                $invoice->items()->createMany($items);
            }

            $this->salesInvoiceService->recalculateTotals($invoice);

            return $invoice->refresh();
        });
    }

    public function createCustomerPayment(array $data): MobileWriteResult
    {
        return $this->idempotentCreate(
            CustomerPayment::class,
            $data,
            fn (string $payloadHash): CustomerPayment => CustomerPayment::query()->create([
                ...$data,
                'created_by' => Auth::id(),
                'client_payload_hash' => $payloadHash,
                'operation_source' => OperationSource::MOBILE_SALES,
            ]),
        );
    }

    public function updateCustomerPayment(CustomerPayment $payment, array $data): CustomerPayment
    {
        return DB::transaction(function () use ($payment, $data): CustomerPayment {
            $payment->fill($data)->save();

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
                $salesReturn = SalesReturn::query()->create([
                    ...$data,
                    'created_by' => Auth::id(),
                    'client_payload_hash' => $payloadHash,
                    'operation_source' => OperationSource::MOBILE_SALES,
                ]);

                $salesReturn->items()->createMany($items);
                $this->salesReturnService->recalculateTotals($salesReturn);

                return $salesReturn->refresh();
            },
        );
    }

    public function updateSalesReturn(SalesReturn $salesReturn, array $data): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn, $data): SalesReturn {
            $hasItems = array_key_exists('items', $data);
            $items = Arr::pull($data, 'items', []);

            $salesReturn->fill($data)->save();

            if ($hasItems) {
                $salesReturn->items()->delete();
                $salesReturn->items()->createMany($items);
            }

            $this->salesReturnService->recalculateTotals($salesReturn);

            return $salesReturn->refresh();
        });
    }

    public function createVehicleExpense(
        array $data,
        ?UploadedFile $receipt = null,
    ): MobileWriteResult {
        unset($data['receipt'], $data['remove_receipt']);

        return $this->idempotentCreate(
            VehicleExpense::class,
            $this->payloadWithFileFingerprint($data, $receipt),
            function (string $payloadHash) use ($data, $receipt): VehicleExpense {
                $receiptPath = $receipt?->store('vehicle-expense-receipts', 'public');

                try {
                    return VehicleExpense::query()->create([
                        ...$data,
                        'receipt_path' => $receiptPath,
                        'created_by' => Auth::id(),
                        'client_payload_hash' => $payloadHash,
                        'operation_source' => OperationSource::MOBILE_DRIVER,
                    ]);
                } catch (Throwable $exception) {
                    if ($receiptPath) {
                        Storage::disk('public')->delete($receiptPath);
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
        $removeReceipt = filter_var(
            Arr::pull($data, 'remove_receipt', false),
            FILTER_VALIDATE_BOOLEAN,
        );
        $newReceiptPath = $receipt?->store('vehicle-expense-receipts', 'public');
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
                Storage::disk('public')->delete($newReceiptPath);
            }

            throw $exception;
        }

        if (($newReceiptPath !== null || $removeReceipt) && $oldReceiptPath) {
            Storage::disk('public')->delete($oldReceiptPath);
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
            Storage::disk('public')->delete($receiptPath);
        }
    }

    /**
     * @template TModel of Model
     * @param class-string<TModel> $modelClass
     * @param array<string, mixed> $payload
     * @param Closure(string): TModel $creator
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
     *  @return array<string, mixed>
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
