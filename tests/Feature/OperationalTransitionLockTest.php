<?php

namespace Tests\Feature;

use App\Models\DailyClosing;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\Warehouse;
use App\Services\Distribution\DailyClosingService;
use App\Services\Distribution\VehicleExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OperationalTransitionLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_vehicle_expense_cannot_be_approved_twice(): void
    {
        $suffix = uniqid();
        [$vehicle, $warehouse] = $this->vehicleContext($suffix);

        $expense = VehicleExpense::query()->create([
            'expense_number' => 'VEX-LOCK-'.$suffix,
            'expense_date' => today(),
            'vehicle_id' => $vehicle->id,
            'warehouse_id' => $warehouse->id,
            'expense_type' => 'fuel',
            'amount' => 100,
            'payment_method' => 'cash',
            'status' => 'pending',
        ]);

        $firstCopy = VehicleExpense::query()->findOrFail($expense->id);
        $staleCopy = VehicleExpense::query()->findOrFail($expense->id);

        app(VehicleExpenseService::class)->approve($firstCopy);

        try {
            app(VehicleExpenseService::class)->approve($staleCopy);
            $this->fail('A stale expense instance must not be approved twice.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'لا يمكن اعتماد مصروف ليس بحالة قيد المراجعة.',
                $exception->getMessage(),
            );
        }

        $this->assertSame('approved', $expense->refresh()->status);
    }

    public function test_stale_daily_closing_cannot_be_confirmed_twice(): void
    {
        $suffix = uniqid();
        [, $warehouse] = $this->vehicleContext($suffix);

        $closing = DailyClosing::query()->create([
            'closing_number' => 'DCL-LOCK-'.$suffix,
            'closing_date' => today(),
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'actual_cash_amount' => 0,
        ]);

        $firstCopy = DailyClosing::query()->findOrFail($closing->id);
        $staleCopy = DailyClosing::query()->findOrFail($closing->id);

        app(DailyClosingService::class)->confirm($firstCopy);

        try {
            app(DailyClosingService::class)->confirm($staleCopy);
            $this->fail('A stale daily closing instance must not be confirmed twice.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'لا يمكن اعتماد إغلاق يوم ليس بحالة مسودة.',
                $exception->getMessage(),
            );
        }

        $this->assertSame('confirmed', $closing->refresh()->status);
    }

    /** @return array{Vehicle, Warehouse} */
    private function vehicleContext(string $suffix): array
    {
        $vehicle = Vehicle::query()->create([
            'code' => 'V-LOCK-'.$suffix,
            'plate_number' => 'LOCK-'.$suffix,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'W-LOCK-'.$suffix,
            'name' => 'Lock Warehouse '.$suffix,
            'type' => 'vehicle',
            'vehicle_id' => $vehicle->id,
            'status' => 'active',
        ]);

        return [$vehicle, $warehouse];
    }
}
