<?php

namespace App\Services\Api;

use App\Models\MobileSyncChange;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class MobileSyncVersionService
{
    public function forRecord(string $entity, Model|int $record): string
    {
        $recordId = $record instanceof Model
            ? (int) $record->getKey()
            : $record;
        $cursor = (int) (MobileSyncChange::query()
            ->where('entity', $entity)
            ->where('record_id', $recordId)
            ->max('id') ?? 0);

        if ($cursor <= 0) {
            throw new RuntimeException(
                "لا يمكن تحديد نسخة المزامنة للسجل [{$entity}:{$recordId}].",
            );
        }

        return 'c:'.$cursor;
    }

    public function matches(string $baseVersion, string $currentVersion): bool
    {
        return hash_equals($currentVersion, $baseVersion);
    }
}
