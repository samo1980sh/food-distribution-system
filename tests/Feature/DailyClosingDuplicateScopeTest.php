<?php

namespace Tests\Feature;

use App\Models\DailyClosing;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class DailyClosingDuplicateScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_duplicate_active_closing_for_same_scope_is_rejected(): void
    {
        $warehouse = $this->createWarehouse();

        DailyClosing::query()->create([
            'closing_number' => 'DCL-DUP-1',
            'closing_date' => '2026-07-04',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $this->expectException(RuntimeException::class);

        DailyClosing::query()->create([
            'closing_number' => 'DCL-DUP-2',
            'closing_date' => '2026-07-04',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);
    }

    public function test_cancelled_closing_does_not_block_new_closing_for_same_scope(): void
    {
        $warehouse = $this->createWarehouse();

        DailyClosing::query()->create([
            'closing_number' => 'DCL-CANCELLED-1',
            'closing_date' => '2026-07-04',
            'warehouse_id' => $warehouse->id,
            'status' => 'cancelled',
        ]);

        $closing = DailyClosing::query()->create([
            'closing_number' => 'DCL-CANCELLED-2',
            'closing_date' => '2026-07-04',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $this->assertSame('draft', $closing->status);
        $this->assertSame('2026-07-04|'.$warehouse->id, $closing->active_scope_key);
    }

    public function test_scoped_closing_is_rejected_when_same_date_and_warehouse_already_has_active_closing(): void
    {
        $vehicle = Vehicle::query()->create([
            'code' => 'V-DCL-'.uniqid(),
            'plate_number' => 'DCL-'.uniqid(),
            'status' => 'active',
        ]);

        $salesRepresentative = Employee::query()->create([
            'employee_code' => 'E-DCL-'.uniqid(),
            'name' => 'Daily Closing Representative',
            'type' => 'sales_representative',
            'status' => 'active',
        ]);

        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'W-DCL-'.uniqid(),
            'name' => 'Daily Closing Vehicle Warehouse',
            'type' => 'vehicle',
            'status' => 'active',
        ]);

        DailyClosing::query()->create([
            'closing_number' => 'DCL-OVERLAP-1',
            'closing_date' => '2026-07-04',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $this->expectException(RuntimeException::class);

        DailyClosing::query()->create([
            'closing_number' => 'DCL-OVERLAP-2',
            'closing_date' => '2026-07-04',
            'vehicle_id' => $vehicle->id,
            'warehouse_id' => $warehouse->id,
            'sales_representative_id' => $salesRepresentative->id,
            'status' => 'draft',
        ]);
    }

    public function test_cancelling_active_closing_releases_date_and_warehouse_scope(): void
    {
        $warehouse = $this->createWarehouse();

        $closing = DailyClosing::query()->create([
            'closing_number' => 'DCL-RELEASE-1',
            'closing_date' => '2026-07-04',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $closing->forceFill(['status' => 'cancelled'])->save();

        $this->assertNull($closing->refresh()->active_scope_key);

        $newClosing = DailyClosing::query()->create([
            'closing_number' => 'DCL-RELEASE-2',
            'closing_date' => '2026-07-04',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $this->assertSame('2026-07-04|'.$warehouse->id, $newClosing->active_scope_key);
    }

    private function createWarehouse(): Warehouse
    {
        $suffix = uniqid();

        return Warehouse::query()->create([
            'code' => 'W-DCL-'.$suffix,
            'name' => 'Daily Closing Warehouse '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);
    }
}
