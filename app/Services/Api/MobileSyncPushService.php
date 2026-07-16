<?php

namespace App\Services\Api;

use App\Exceptions\Api\OperationalApiException;
use App\Models\MobileSyncPushBatch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class MobileSyncPushService
{
    public function __construct(
        private readonly MobileSyncContextService $contextService,
        private readonly MobileOfflineSyncService $offlineSyncService,
        private readonly MobileSyncPushOperationService $operationService,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $operations
     * @return array<string, mixed>
     */
    public function push(
        User $user,
        Request $request,
        string $contextKey,
        string $batchId,
        array $operations,
    ): array {
        $currentContextKey = $this->contextService->key($user);

        if (! hash_equals($currentContextKey, $contextKey)) {
            throw new OperationalApiException(
                'تغير نطاق الوصول أو الصلاحيات. يجب سحب حالة المزامنة الجديدة قبل رفع العمليات.',
                'sync_context_changed',
                409,
                [
                    'sync' => [
                        'reset_required' => true,
                        'context_key' => $currentContextKey,
                        'cursor' => 0,
                    ],
                ],
            );
        }

        $deviceId = $this->contextService->deviceId($request);
        $requestHash = $this->hash([
            'context_key' => $contextKey,
            'batch_id' => $batchId,
            'operations' => $operations,
        ]);
        $claim = $this->claimBatch(
            $user,
            $deviceId,
            $batchId,
            $requestHash,
            count($operations),
        );

        if (is_array($claim['response'])) {
            return $claim['response'];
        }

        $batch = $claim['batch'];

        try {
            $results = [];

            foreach ($operations as $operation) {
                $results[] = $this->operationService->process(
                    $user,
                    $request,
                    $deviceId,
                    $batchId,
                    $operation,
                );
            }

            $summary = $this->summary($results);
            $response = [
                'batch_id' => $batchId,
                'replayed' => false,
                'context_key' => $currentContextKey,
                'server_cursor' => $this->offlineSyncService->currentCursor(),
                'summary' => $summary,
                'results' => $results,
            ];

            $batch->forceFill([
                'status' => 'completed',
                'applied_count' => $summary['applied'],
                'replayed_count' => $summary['replayed'],
                'conflict_count' => $summary['conflicts'],
                'failed_count' => $summary['failed'],
                'response_payload' => $response,
                'processed_at' => now(),
            ])->save();

            return $response;
        } catch (Throwable $exception) {
            $batch->delete();

            throw $exception;
        }
    }

    /**
     * @return array{batch: MobileSyncPushBatch, response: ?array}
     */
    private function claimBatch(
        User $user,
        string $deviceId,
        string $batchId,
        string $requestHash,
        int $operationCount,
    ): array {
        try {
            return DB::transaction(function () use (
                $user,
                $deviceId,
                $batchId,
                $requestHash,
                $operationCount,
            ): array {
                $batch = MobileSyncPushBatch::query()
                    ->where('user_id', $user->getKey())
                    ->where('device_id', $deviceId)
                    ->where('batch_id', $batchId)
                    ->lockForUpdate()
                    ->first();

                if ($batch === null) {
                    $batch = MobileSyncPushBatch::query()->create([
                        'user_id' => (int) $user->getKey(),
                        'device_id' => $deviceId,
                        'batch_id' => $batchId,
                        'request_hash' => $requestHash,
                        'status' => 'processing',
                        'operation_count' => $operationCount,
                    ]);

                    return ['batch' => $batch, 'response' => null];
                }

                if (! hash_equals((string) $batch->request_hash, $requestHash)) {
                    throw new OperationalApiException(
                        'تم استخدام batch_id نفسه سابقاً مع محتوى مختلف.',
                        'batch_idempotency_conflict',
                        409,
                    );
                }

                if ($batch->status === 'completed' && is_array($batch->response_payload)) {
                    return [
                        'batch' => $batch,
                        'response' => [
                            ...$batch->response_payload,
                            'replayed' => true,
                        ],
                    ];
                }

                $timeout = (int) config('mobile_api.sync_push_processing_timeout_seconds', 300);
                $isStale = $batch->updated_at === null
                    || $batch->updated_at->lte(now()->subSeconds($timeout));

                if (! $isStale) {
                    throw new OperationalApiException(
                        'دفعة المزامنة نفسها ما زالت قيد المعالجة. أعد المحاولة لاحقاً.',
                        'sync_batch_in_progress',
                        409,
                    );
                }

                $batch->forceFill([
                    'status' => 'processing',
                    'operation_count' => $operationCount,
                    'applied_count' => 0,
                    'replayed_count' => 0,
                    'conflict_count' => 0,
                    'failed_count' => 0,
                    'response_payload' => null,
                    'processed_at' => null,
                ])->save();

                return ['batch' => $batch, 'response' => null];
            });
        } catch (QueryException $exception) {
            $batch = MobileSyncPushBatch::query()
                ->where('user_id', $user->getKey())
                ->where('device_id', $deviceId)
                ->where('batch_id', $batchId)
                ->first();

            if ($batch === null) {
                throw $exception;
            }

            if (! hash_equals((string) $batch->request_hash, $requestHash)) {
                throw new OperationalApiException(
                    'تم استخدام batch_id نفسه سابقاً مع محتوى مختلف.',
                    'batch_idempotency_conflict',
                    409,
                );
            }

            if ($batch->status === 'completed' && is_array($batch->response_payload)) {
                return [
                    'batch' => $batch,
                    'response' => [
                        ...$batch->response_payload,
                        'replayed' => true,
                    ],
                ];
            }

            throw new OperationalApiException(
                'دفعة المزامنة نفسها ما زالت قيد المعالجة. أعد المحاولة لاحقاً.',
                'sync_batch_in_progress',
                409,
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $results
     * @return array{total: int, applied: int, replayed: int, conflicts: int, failed: int}
     */
    private function summary(array $results): array
    {
        return [
            'total' => count($results),
            'applied' => count(array_filter($results, static fn (array $result): bool => $result['status'] === 'applied')),
            'replayed' => count(array_filter($results, static fn (array $result): bool => $result['status'] === 'replayed')),
            'conflicts' => count(array_filter($results, static fn (array $result): bool => $result['status'] === 'conflict')),
            'failed' => count(array_filter($results, static fn (array $result): bool => $result['status'] === 'failed')),
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
