<?php

namespace Tests\Feature;

use App\Models\DailyClosing;
use App\Models\Warehouse;
use App\Services\Distribution\DailyClosingGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class DailyClosingGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_confirmed_daily_closing_blocks_same_date_and_warehouse(): void
    {
        $warehouse = $this->createWarehouse();

        DailyClosing::query()->create([
            'closing_number' => 'DCL-GUARD-1',
            'closing_date' => '2026-07-04',
            'warehouse_id' => $warehouse->id,
            'status' => 'confirmed',
        ]);

        $this->expectException(RuntimeException::class);

        app(DailyClosingGuard::class)->ensureOpen('2026-07-04', $warehouse->id);
    }

    public function test_cancelled_daily_closing_does_not_block_same_date_and_warehouse(): void
    {
        $warehouse = $this->createWarehouse();

        DailyClosing::query()->create([
            'closing_number' => 'DCL-GUARD-2',
            'closing_date' => '2026-07-04',
            'warehouse_id' => $warehouse->id,
            'status' => 'cancelled',
        ]);

        app(DailyClosingGuard::class)->ensureOpen('2026-07-04', $warehouse->id);

        $this->assertTrue(true);
    }

    public function test_confirmed_daily_closing_does_not_block_different_warehouse(): void
    {
        $closedWarehouse = $this->createWarehouse();
        $openWarehouse = $this->createWarehouse();

        DailyClosing::query()->create([
            'closing_number' => 'DCL-GUARD-3',
            'closing_date' => '2026-07-04',
            'warehouse_id' => $closedWarehouse->id,
            'status' => 'confirmed',
        ]);

        app(DailyClosingGuard::class)->ensureOpen('2026-07-04', $openWarehouse->id);

        $this->assertTrue(true);
    }

    private function createWarehouse(): Warehouse
    {
        $suffix = uniqid();

        return Warehouse::query()->create([
            'code' => 'W-GUARD-'.$suffix,
            'name' => 'Daily Closing Guard Warehouse '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);
    }
}