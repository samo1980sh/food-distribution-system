<?php

namespace App\Services\Api;

use App\Support\Api\MobileSyncEntityRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MobileSyncChangeRecorder
{
    private static bool $tableReady = false;

    public function __construct(
        private readonly MobileSyncScopeService $scopeService,
    ) {
    }

    public function upsert(Model $model): void
    {
        $this->record(
            $model,
            'upsert',
            $this->scopeService->snapshot($model),
        );
    }

    /** @param array<string, mixed>|null $snapshot */
    public function deletion(Model $model, ?array $snapshot = null): void
    {
        $this->record(
            $model,
            'delete',
            $snapshot ?? $this->scopeService->snapshot($model),
        );
    }

    /** @param array<string, mixed> $snapshot */
    public function deletionByIdentity(
        string $entity,
        int $recordId,
        array $snapshot,
    ): void {
        $this->insert($entity, $recordId, 'delete', $snapshot);
    }

    /** @param array<string, mixed> $snapshot */
    private function record(Model $model, string $operation, array $snapshot): void
    {
        $entity = MobileSyncEntityRegistry::entityForModel($model);

        if ($entity === null || $model->getKey() === null) {
            return;
        }

        $this->insert(
            $entity,
            (int) $model->getKey(),
            $operation,
            $snapshot,
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function insert(
        string $entity,
        int $recordId,
        string $operation,
        array $snapshot,
    ): void {
        if (! array_key_exists($entity, MobileSyncEntityRegistry::definitions())
            || $recordId <= 0
            || ! $this->tableIsReady()) {
            return;
        }

        DB::table('mobile_sync_changes')->insert([
            'entity' => $entity,
            'record_id' => $recordId,
            'operation' => $operation,
            'scope_snapshot' => $snapshot === []
                ? null
                : json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'changed_at' => now(),
        ]);
    }

    private function tableIsReady(): bool
    {
        if (self::$tableReady) {
            return true;
        }

        if (! Schema::hasTable('mobile_sync_changes')) {
            return false;
        }

        return self::$tableReady = true;
    }
}
