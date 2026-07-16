<?php

namespace App\Console\Commands;

use App\Models\MobileSyncChange;
use App\Models\MobileSyncCheckpoint;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class PruneMobileSyncChanges extends Command
{
    protected $signature = 'mobile-sync:prune
        {--days= : Override the configured retention period}
        {--apply : Compact matching rows instead of running a dry run}';

    protected $description = 'Compact old mobile sync changes while preserving the latest full-sync state for every record.';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days')
            ?: config('mobile_api.sync_retention_days', 90)));
        $cutoff = now()->subDays($days);
        $eligible = $this->eligibleQuery($cutoff);
        $count = (clone $eligible)->count();
        $prunedThroughCursor = (int) ((clone $eligible)->max('mobile_sync_changes.id') ?? 0);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Retention days', $days],
                ['Cutoff', $cutoff->toIso8601String()],
                ['Rows eligible for compaction', $count],
                ['Pruned-through cursor', $prunedThroughCursor],
                ['Mode', $this->option('apply') ? 'apply' : 'dry-run'],
            ],
        );

        if (! $this->option('apply') || $count === 0) {
            return self::SUCCESS;
        }

        $deleted = 0;

        do {
            $ids = $this->eligibleQuery($cutoff)
                ->orderBy('mobile_sync_changes.id')
                ->limit(1000)
                ->pluck('mobile_sync_changes.id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += MobileSyncChange::query()
                ->whereIn('id', $ids->all())
                ->delete();
        } while (true);

        $checkpoint = MobileSyncCheckpoint::singleton();
        $checkpoint->forceFill([
            'pruned_through_cursor' => max(
                (int) $checkpoint->pruned_through_cursor,
                $prunedThroughCursor,
            ),
            'last_compacted_at' => now(),
        ])->save();

        $this->info("Compacted {$deleted} mobile sync change rows.");

        return self::SUCCESS;
    }

    private function eligibleQuery(mixed $cutoff): Builder
    {
        return MobileSyncChange::query()
            ->where('mobile_sync_changes.changed_at', '<', $cutoff)
            ->where(function (Builder $query): void {
                $query
                    ->where('mobile_sync_changes.operation', 'delete')
                    ->orWhereExists(function ($newer): void {
                        $newer
                            ->selectRaw('1')
                            ->from('mobile_sync_changes as newer')
                            ->whereColumn('newer.entity', 'mobile_sync_changes.entity')
                            ->whereColumn('newer.record_id', 'mobile_sync_changes.record_id')
                            ->whereColumn('newer.id', '>', 'mobile_sync_changes.id');
                    });
            });
    }
}
