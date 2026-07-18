<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use App\Services\Sales\SalesInvoiceService;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationalContextValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_route_must_belong_to_customer_area(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');

        $this->assertValidationError(
            fn (): Customer => Customer::query()->create([
                'code' => 'CTX-CUS-MISMATCH',
                'name' => 'عميل بسياق خاطئ',
                'area_id' => $first['area']->id,
                'route_id' => $second['route']->id,
                'status' => 'active',
            ]),
            'route_id',
        );
    }

    public function test_route_team_must_be_active_and_qualified_for_operational_roles(): void
    {
        $context = $this->context('A');
        $wrongDriver = Employee::query()->create([
            'employee_code' => 'CTX-WRONG-DRIVER',
            'name' => 'موظف غير مؤهل للقيادة',
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
        $inactiveRepresentative = Employee::query()->create([
            'employee_code' => 'CTX-INACTIVE-REP',
            'name' => 'مندوب غير فعال',
            'type' => 'sales_representative',
            'status' => 'inactive',
        ]);

        $this->assertValidationError(
            fn (): DistributionRoute => DistributionRoute::query()->create([
                'area_id' => $context['area']->id,
                'vehicle_id' => $context['vehicle']->id,
                'driver_id' => $wrongDriver->id,
                'code' => 'CTX-ROUTE-WRONG-DRIVER',
                'name' => 'خط بسائق غير مؤهل',
                'status' => 'active',
            ]),
            'driver_id',
        );

        $this->assertValidationError(
            fn (): DistributionRoute => DistributionRoute::query()->create([
                'area_id' => $context['area']->id,
                'vehicle_id' => $context['vehicle']->id,
                'sales_representative_id' => $inactiveRepresentative->id,
                'code' => 'CTX-ROUTE-INACTIVE-REP',
                'name' => 'خط بمندوب غير فعال',
                'status' => 'active',
            ]),
            'sales_representative_id',
        );
    }

    public function test_vehicle_warehouse_type_and_uniqueness_are_enforced(): void
    {
        $context = $this->context('A');

        $this->assertValidationError(
            fn (): Warehouse => Warehouse::query()->create([
                'code' => 'CTX-WH-DUPLICATE',
                'name' => 'مستودع سيارة مكرر',
                'type' => 'vehicle',
                'vehicle_id' => $context['vehicle']->id,
                'status' => 'active',
            ]),
            'vehicle_id',
        );

        $this->assertValidationError(
            fn (): Warehouse => Warehouse::query()->create([
                'code' => 'CTX-WH-MAIN-VEHICLE',
                'name' => 'مستودع رئيسي مرتبط بسيارة',
                'type' => 'main',
                'vehicle_id' => $context['vehicle']->id,
                'status' => 'active',
            ]),
            'vehicle_id',
        );
    }

    public function test_vehicle_warehouse_identity_is_immutable_after_operational_history(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');

        $this->invoice([
            'invoice_number' => 'CTX-INV-WAREHOUSE-HISTORY',
            'customer_id' => $first['customer']->id,
            'vehicle_id' => $first['vehicle']->id,
            'route_id' => $first['route']->id,
            'warehouse_id' => $first['warehouse']->id,
            'sales_representative_id' => $first['representative']->id,
        ]);

        $this->assertValidationError(
            function () use ($first, $second): Warehouse {
                $first['warehouse']->vehicle_id = $second['vehicle']->id;
                $first['warehouse']->save();

                return $first['warehouse'];
            },
            'vehicle_id',
        );
    }

    public function test_invoice_rejects_cross_customer_route_vehicle_warehouse_and_team_context(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');

        $this->assertValidationError(
            fn (): SalesInvoice => $this->invoice([
                'invoice_number' => 'CTX-INV-ROUTE',
                'customer_id' => $first['customer']->id,
                'vehicle_id' => $second['vehicle']->id,
                'route_id' => $second['route']->id,
                'warehouse_id' => $second['warehouse']->id,
                'sales_representative_id' => $second['representative']->id,
            ]),
            'route_id',
        );

        $this->assertValidationError(
            fn (): SalesInvoice => $this->invoice([
                'invoice_number' => 'CTX-INV-WAREHOUSE',
                'customer_id' => $first['customer']->id,
                'vehicle_id' => $first['vehicle']->id,
                'route_id' => $first['route']->id,
                'warehouse_id' => $second['warehouse']->id,
                'sales_representative_id' => $first['representative']->id,
            ]),
            'warehouse_id',
        );

        $this->assertValidationError(
            fn (): SalesInvoice => $this->invoice([
                'invoice_number' => 'CTX-INV-REPRESENTATIVE',
                'customer_id' => $first['customer']->id,
                'vehicle_id' => $first['vehicle']->id,
                'route_id' => $first['route']->id,
                'warehouse_id' => $first['warehouse']->id,
                'sales_representative_id' => $second['representative']->id,
            ]),
            'sales_representative_id',
        );
    }

    public function test_linked_return_and_payment_must_match_original_invoice_context(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');
        $invoice = $this->invoice([
            'invoice_number' => 'CTX-INV-SOURCE',
            'customer_id' => $first['customer']->id,
            'vehicle_id' => $first['vehicle']->id,
            'route_id' => $first['route']->id,
            'warehouse_id' => $first['warehouse']->id,
            'sales_representative_id' => $first['representative']->id,
            'status' => 'confirmed',
        ]);

        $this->assertValidationError(
            fn (): SalesReturn => SalesReturn::query()->create([
                'return_number' => 'CTX-RETURN-MISMATCH',
                'customer_id' => $first['customer']->id,
                'sales_invoice_id' => $invoice->id,
                'vehicle_id' => $first['vehicle']->id,
                'route_id' => $first['route']->id,
                'warehouse_id' => $second['warehouse']->id,
                'sales_representative_id' => $first['representative']->id,
                'return_date' => today(),
                'status' => 'draft',
            ]),
            'warehouse_id',
        );

        $this->assertValidationError(
            fn (): CustomerPayment => CustomerPayment::query()->create([
                'payment_number' => 'CTX-PAYMENT-MISMATCH',
                'customer_id' => $first['customer']->id,
                'sales_invoice_id' => $invoice->id,
                'vehicle_id' => $second['vehicle']->id,
                'payment_date' => today(),
                'payment_method' => 'cash',
                'status' => 'draft',
                'amount' => 5,
            ]),
            'vehicle_id',
        );
    }

    public function test_confirmed_records_keep_their_historical_context_after_route_reassignment(): void
    {
        $context = $this->context('A');
        $invoice = $this->invoice([
            'invoice_number' => 'CTX-INV-HISTORICAL',
            'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id,
            'route_id' => $context['route']->id,
            'warehouse_id' => $context['warehouse']->id,
            'sales_representative_id' => $context['representative']->id,
            'status' => 'confirmed',
        ]);
        $replacement = Employee::query()->create([
            'employee_code' => 'CTX-REP-REPLACEMENT',
            'name' => 'مندوب بديل',
            'type' => 'sales_representative',
            'status' => 'active',
        ]);

        $context['route']->update([
            'sales_representative_id' => $replacement->id,
        ]);

        $refreshed = app(SalesInvoiceService::class)
            ->refreshFinancialBalance($invoice);

        $this->assertSame(
            $context['representative']->id,
            (int) $refreshed->sales_representative_id,
        );
        $this->assertSame('10.00', $refreshed->remaining_amount);
    }

    public function test_load_expense_and_closing_reject_cross_vehicle_context(): void
    {
        $first = $this->context('A');
        $second = $this->context('B');

        $this->assertValidationError(
            fn (): VehicleLoad => VehicleLoad::query()->create([
                'load_number' => 'CTX-LOAD-MISMATCH',
                'vehicle_id' => $first['vehicle']->id,
                'route_id' => $first['route']->id,
                'driver_id' => $first['driver']->id,
                'sales_representative_id' => $first['representative']->id,
                'from_warehouse_id' => $first['source_warehouse']->id,
                'to_warehouse_id' => $second['warehouse']->id,
                'load_date' => today(),
                'status' => 'draft',
            ]),
            'to_warehouse_id',
        );

        $this->assertValidationError(
            fn (): VehicleExpense => VehicleExpense::query()->create([
                'expense_number' => 'CTX-EXPENSE-MISMATCH',
                'expense_date' => today(),
                'vehicle_id' => $first['vehicle']->id,
                'warehouse_id' => $first['warehouse']->id,
                'route_id' => $second['route']->id,
                'driver_id' => $first['driver']->id,
                'expense_type' => 'fuel',
                'amount' => 10,
                'payment_method' => 'cash',
                'status' => 'pending',
            ]),
            'vehicle_id',
        );

        $this->assertValidationError(
            fn (): DailyClosing => DailyClosing::query()->create([
                'closing_number' => 'CTX-CLOSING-MISMATCH',
                'closing_date' => today(),
                'vehicle_id' => $first['vehicle']->id,
                'warehouse_id' => $second['warehouse']->id,
                'status' => 'draft',
            ]),
            'warehouse_id',
        );
    }

    public function test_consistent_operational_context_is_accepted(): void
    {
        $context = $this->context('A');

        $invoice = $this->invoice([
            'invoice_number' => 'CTX-INV-VALID',
            'customer_id' => $context['customer']->id,
            'vehicle_id' => $context['vehicle']->id,
            'route_id' => $context['route']->id,
            'warehouse_id' => $context['warehouse']->id,
            'sales_representative_id' => $context['representative']->id,
        ]);

        $this->assertTrue($invoice->exists);

        $load = VehicleLoad::query()->create([
            'load_number' => 'CTX-LOAD-VALID',
            'vehicle_id' => $context['vehicle']->id,
            'route_id' => $context['route']->id,
            'driver_id' => $context['driver']->id,
            'sales_representative_id' => $context['representative']->id,
            'from_warehouse_id' => $context['source_warehouse']->id,
            'to_warehouse_id' => $context['warehouse']->id,
            'load_date' => today(),
            'status' => 'draft',
        ]);

        $this->assertTrue($load->exists);
    }

    /** @return array<string, mixed> */
    private function context(string $suffix): array
    {
        $area = Area::query()->create([
            'code' => 'CTX-AREA-'.$suffix,
            'name_ar' => 'منطقة '.$suffix,
            'status' => 'active',
        ]);
        $vehicle = Vehicle::query()->create([
            'code' => 'CTX-VEH-'.$suffix,
            'plate_number' => 'CTX-PLATE-'.$suffix,
            'status' => 'active',
        ]);
        $sourceWarehouse = Warehouse::query()->create([
            'code' => 'CTX-SOURCE-'.$suffix,
            'name' => 'مستودع مصدر '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);
        $warehouse = Warehouse::query()->create([
            'vehicle_id' => $vehicle->id,
            'code' => 'CTX-WH-'.$suffix,
            'name' => 'مستودع سيارة '.$suffix,
            'type' => 'vehicle',
            'status' => 'active',
        ]);
        $driver = Employee::query()->create([
            'employee_code' => 'CTX-DRV-'.$suffix,
            'name' => 'سائق '.$suffix,
            'type' => 'driver',
            'status' => 'active',
        ]);
        $representative = Employee::query()->create([
            'employee_code' => 'CTX-REP-'.$suffix,
            'name' => 'مندوب '.$suffix,
            'type' => 'sales_representative',
            'status' => 'active',
        ]);
        $route = DistributionRoute::query()->create([
            'area_id' => $area->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'sales_representative_id' => $representative->id,
            'code' => 'CTX-ROUTE-'.$suffix,
            'name' => 'خط '.$suffix,
            'status' => 'active',
        ]);
        $customer = Customer::query()->create([
            'code' => 'CTX-CUS-'.$suffix,
            'name' => 'عميل '.$suffix,
            'area_id' => $area->id,
            'route_id' => $route->id,
            'status' => 'active',
        ]);

        return [
            'area' => $area,
            'vehicle' => $vehicle,
            'source_warehouse' => $sourceWarehouse,
            'warehouse' => $warehouse,
            'driver' => $driver,
            'representative' => $representative,
            'route' => $route,
            'customer' => $customer,
        ];
    }

    /** @param array<string, mixed> $data */
    private function invoice(array $data): SalesInvoice
    {
        return SalesInvoice::query()->create([
            ...$data,
            'invoice_date' => today(),
            'status' => $data['status'] ?? 'draft',
            'payment_type' => 'credit',
            'subtotal' => 10,
            'total_amount' => 10,
            'remaining_amount' => 10,
        ]);
    }

    private function assertValidationError(Closure $callback, string $field): void
    {
        try {
            $callback();
            $this->fail("Expected a validation error for [{$field}].");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
