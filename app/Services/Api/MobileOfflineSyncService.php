<?php

namespace App\Services\Api;

use App\Exceptions\Api\OperationalApiException;
use App\Models\MobileSyncChange;
use App\Models\MobileSyncCheckpoint;
use App\Models\MobileSyncState;
use App\Models\User;
use App\Support\Api\MobileSyncEntityRegistry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MobileOfflineSyncService
{
    public function __construct(
        private readonly MobileSyncContextService $contextService,
        private readonly MobileSyncScopeService $scopeService,
    ) {
    }

    /** @return array<string, mixed> */
    public function status(User $user, Request $request): array
    {
        $contextKey = $this->contextService->key($user);
        $state = $this->state($user, $request, $contextKey, false);
        $currentCursor = $this->currentCursor();
        $minimumCursor = $this->minimumCursor();

        $contextChanged = $state !== null
            && ! hash_equals((string) $state->context_key, $contextKey);
        $cursorExpired = $state !== null
            && (int) $state->last_pull_cursor > 0
            && (int) $state->last_pull_cursor < $minimumCursor;

        return [
            'context_key' => $contextKey,
            'registry_version' => MobileSyncEntityRegistry::VERSION,
            'current_cursor' => $currentCursor,
            'minimum_cursor' => $minimumCursor,
            'reset_required' => $contextChanged || $cursorExpired,
            'reset_reason' => $contextChanged
                ? 'sync_context_changed'
                : ($cursorExpired ? 'sync_cursor_expired' : null),
            'device' => [
                'device_id' => $this->contextService->deviceId($request),
                'last_pull_cursor' => (int) ($state?->last_pull_cursor ?? 0),
                'last_pull_at' => $this->iso($state?->last_pull_at),
                'last_full_sync_at' => $this->iso($state?->last_full_sync_at),
            ],
            'limits' => [
                'default_pull' => (int) config('mobile_api.sync_default_pull_limit', 200),
                'max_pull' => (int) config('mobile_api.sync_max_pull_limit', 500),
                'retention_days' => (int) config('mobile_api.sync_retention_days', 90),
            ],
            'entities' => MobileSyncEntityRegistry::entities(),
        ];
    }

    /** @return array<string, mixed> */
    public function pull(
        User $user,
        Request $request,
        int $cursor,
        int $limit,
        ?string $clientContextKey,
    ): array {
        $contextKey = $this->contextService->key($user);
        $state = $this->state($user, $request, $contextKey, true);

        $this->ensureContextIsCurrent(
            $cursor,
            $clientContextKey,
            $contextKey,
            $state,
        );
        $this->ensureCursorIsAvailable($cursor, $contextKey);

        $serverCursor = $this->currentCursor();
        $changes = MobileSyncChange::query()
            ->where('id', '>', $cursor)
            ->where('id', '<=', $serverCursor)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $nextCursor = $changes->isEmpty()
            ? $cursor
            : (int) $changes->last()->getKey();
        $items = [];
        $skipped = 0;

        foreach ($changes as $change) {
            $item = $this->resolveChange($user, $request, $change);

            if ($item === null) {
                $skipped++;

                continue;
            }

            $items[] = $item;
        }

        $hasMore = MobileSyncChange::query()
            ->where('id', '>', $nextCursor)
            ->where('id', '<=', $serverCursor)
            ->exists();

        if (! $hasMore) {
            $nextCursor = $serverCursor;
        }

        $state->forceFill([
            'context_key' => $contextKey,
            'last_pull_cursor' => $nextCursor,
            'last_pull_at' => now(),
            'last_full_sync_at' => $cursor === 0
                ? now()
                : $state->last_full_sync_at,
        ])->save();

        return [
            'context_key' => $contextKey,
            'cursor' => $nextCursor,
            'server_cursor' => $serverCursor,
            'has_more' => $hasMore,
            'changes' => $items,
            'stats' => [
                'scanned' => $changes->count(),
                'returned' => count($items),
                'skipped' => $skipped,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function resolveChange(
        User $user,
        Request $request,
        MobileSyncChange $change,
    ): ?array {
        $entity = (string) $change->entity;
        $definitions = MobileSyncEntityRegistry::definitions();
        $definition = $definitions[$entity] ?? null;

        if ($definition === null || ! $this->canReadEntity($user, $definition['permissions'])) {
            return null;
        }

        if ($change->operation === 'delete') {
            $snapshot = (array) ($change->scope_snapshot ?? []);

            if (! $this->scopeService->allows($user, $entity, $snapshot)) {
                return null;
            }

            return [
                'cursor' => (int) $change->getKey(),
                'entity' => $entity,
                'operation' => 'delete',
                'record_id' => (int) $change->record_id,
                'version' => null,
                'record' => null,
                'changed_at' => $this->iso($change->changed_at),
            ];
        }

        $modelClass = $definition['model'];
        $record = $modelClass::query()
            ->with($definition['relations'])
            ->find((int) $change->record_id);

        if (! $record instanceof Model) {
            return null;
        }

        $resourceClass = $definition['resource'];

        return [
            'cursor' => (int) $change->getKey(),
            'entity' => $entity,
            'operation' => 'upsert',
            'record_id' => (int) $record->getKey(),
            'version' => $this->iso($record->getAttribute('updated_at')),
            'record' => $resourceClass::make($record)->resolve($request),
            'changed_at' => $this->iso($change->changed_at),
        ];
    }

    /** @param list<string> $permissions */
    private function canReadEntity(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    private function ensureContextIsCurrent(
        int $cursor,
        ?string $clientContextKey,
        string $contextKey,
        MobileSyncState $state,
    ): void {
        $clientMismatch = $clientContextKey !== null
            && ! hash_equals($clientContextKey, $contextKey);
        $storedMismatch = ! hash_equals((string) $state->context_key, $contextKey);

        if ($cursor === 0) {
            return;
        }

        if (! $clientMismatch && ! $storedMismatch) {
            return;
        }

        throw $this->resetRequired(
            'تغير نطاق الوصول أو الصلاحيات. يجب مسح البيانات المحلية وإجراء مزامنة كاملة.',
            'sync_context_changed',
            $contextKey,
        );
    }

    private function ensureCursorIsAvailable(int $cursor, string $contextKey): void
    {
        $minimumCursor = $this->minimumCursor();

        if ($cursor === 0 || $minimumCursor === 0 || $cursor >= $minimumCursor) {
            return;
        }

        throw $this->resetRequired(
            'مؤشر المزامنة قديم ولم يعد متاحاً. يجب إجراء مزامنة كاملة.',
            'sync_cursor_expired',
            $contextKey,
        );
    }

    private function resetRequired(
        string $message,
        string $code,
        string $contextKey,
    ): OperationalApiException {
        return new OperationalApiException(
            $message,
            $code,
            409,
            [
                'sync' => [
                    'reset_required' => true,
                    'context_key' => $contextKey,
                    'cursor' => 0,
                ],
            ],
        );
    }

    private function state(
        User $user,
        Request $request,
        string $contextKey,
        bool $create,
    ): ?MobileSyncState {
        $attributes = [
            'user_id' => (int) $user->getKey(),
            'device_id' => $this->contextService->deviceId($request),
        ];

        if (! $create) {
            return MobileSyncState::query()->where($attributes)->first();
        }

        return MobileSyncState::query()->firstOrCreate(
            $attributes,
            [
                'context_key' => $contextKey,
                'last_pull_cursor' => 0,
            ],
        );
    }

    public function currentCursor(): int
    {
        return max(
            (int) (MobileSyncChange::query()->max('id') ?? 0),
            $this->minimumCursor(),
        );
    }

    public function minimumCursor(): int
    {
        return (int) (MobileSyncCheckpoint::query()
            ->whereKey(MobileSyncCheckpoint::SINGLETON_ID)
            ->value('pruned_through_cursor') ?? 0);
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return Carbon::parse($value)->toIso8601String();
    }
}
