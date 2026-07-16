<?php

namespace App\Observers;

use App\Services\Api\MobileSyncCascadeService;
use App\Services\Api\MobileSyncChangeRecorder;
use App\Services\Api\MobileSyncScopeService;
use Illuminate\Database\Eloquent\Model;

class MobileSyncChangeObserver
{
    private const ORIGINAL_SCOPE_RELATION = '__mobile_sync_original_scope';
    private const DELETED_SCOPE_RELATION = '__mobile_sync_deleted_scope';
    private const CASCADE_PLAN_RELATION = '__mobile_sync_cascade_plan';

    public function __construct(
        private readonly MobileSyncChangeRecorder $recorder,
        private readonly MobileSyncScopeService $scopeService,
        private readonly MobileSyncCascadeService $cascadeService,
    ) {
    }

    public function created(Model $model): void
    {
        $this->recorder->upsert($model);
    }

    public function updating(Model $model): void
    {
        $original = $model->newInstance([], true);
        $original->setRawAttributes($model->getRawOriginal(), true);

        $model->setRelation(
            self::ORIGINAL_SCOPE_RELATION,
            $this->scopeService->snapshot($original),
        );
    }

    public function updated(Model $model): void
    {
        $originalScope = $model->getRelation(self::ORIGINAL_SCOPE_RELATION);
        $currentScope = $this->scopeService->snapshot($model);

        if (is_array($originalScope) && $originalScope !== $currentScope) {
            $this->recorder->deletion($model, $originalScope);
        }

        $model->unsetRelation(self::ORIGINAL_SCOPE_RELATION);
        $this->recorder->upsert($model);
    }

    public function deleting(Model $model): void
    {
        $model->setRelation(
            self::DELETED_SCOPE_RELATION,
            $this->scopeService->snapshot($model),
        );
        $model->setRelation(
            self::CASCADE_PLAN_RELATION,
            $this->cascadeService->capture($model),
        );
    }

    public function deleted(Model $model): void
    {
        $snapshot = $model->getRelation(self::DELETED_SCOPE_RELATION);
        $cascadePlan = $model->getRelation(self::CASCADE_PLAN_RELATION);
        $model->unsetRelation(self::DELETED_SCOPE_RELATION);
        $model->unsetRelation(self::CASCADE_PLAN_RELATION);

        $this->recorder->deletion(
            $model,
            is_array($snapshot) ? $snapshot : null,
        );

        if (is_array($cascadePlan)) {
            $this->cascadeService->record($cascadePlan);
        }
    }
}
