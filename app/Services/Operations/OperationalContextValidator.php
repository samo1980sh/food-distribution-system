<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\Employee;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class OperationalContextValidator
{
    public function validateOperationalRecord(Model $record): void
    {
        $contextFields = match (true) {
            $record instanceof SalesInvoice => [
                'customer_id',
                'vehicle_id',
                'route_id',
                'warehouse_id',
                'sales_representative_id',
            ],
            $record instanceof SalesReturn => [
                'customer_id',
                'sales_invoice_id',
                'vehicle_id',
                'route_id',
                'warehouse_id',
                'sales_representative_id',
            ],
            $record instanceof CustomerPayment => [
                'customer_id',
                'sales_invoice_id',
                'vehicle_id',
                'route_id',
                'warehouse_id',
                'sales_representative_id',
            ],
            $record instanceof VehicleLoad => [
                'vehicle_id',
                'route_id',
                'driver_id',
                'sales_representative_id',
                'from_warehouse_id',
                'to_warehouse_id',
            ],
            $record instanceof VehicleExpense => [
                'vehicle_id',
                'warehouse_id',
                'route_id',
                'driver_id',
                'sales_representative_id',
            ],
            $record instanceof DailyClosing => [
                'vehicle_id',
                'route_id',
                'warehouse_id',
                'sales_representative_id',
            ],
            default => [],
        };

        if (! $record->exists || $record->isDirty($contextFields)) {
            $this->validate($record);
        }
    }

    public function validate(Model $record): void
    {
        match (true) {
            $record instanceof Customer => $this->validateCustomer($record),
            $record instanceof DistributionRoute => $this->validateRoute($record),
            $record instanceof Warehouse => $this->validateWarehouse($record),
            $record instanceof SalesInvoice => $this->validateSalesInvoice($record),
            $record instanceof SalesReturn => $this->validateSalesReturn($record),
            $record instanceof CustomerPayment => $this->validateCustomerPayment($record),
            $record instanceof VehicleLoad => $this->validateVehicleLoad($record),
            $record instanceof VehicleExpense => $this->validateVehicleExpense($record),
            $record instanceof DailyClosing => $this->validateDailyClosing($record),
            default => null,
        };
    }

    public function validateCustomer(Customer $customer): void
    {
        $route = $this->route($customer->route_id);

        if ($route === null) {
            return;
        }

        if ($customer->area_id === null) {
            $this->fail('area_id', 'يجب تحديد منطقة العميل عند ربطه بخط توزيع.');
        }

        if ((int) $route->area_id !== (int) $customer->area_id) {
            $this->fail('route_id', 'خط التوزيع المحدد لا يتبع منطقة العميل.');
        }
    }

    public function validateRoute(DistributionRoute $route): void
    {
        $this->assertEmployeeRole(
            $route->driver_id,
            UserRole::DRIVER,
            'driver_id',
            'الموظف المحدد غير مؤهل للعمل كسائق على خط التوزيع.',
        );

        $this->assertEmployeeRole(
            $route->sales_representative_id,
            UserRole::SALES_REPRESENTATIVE,
            'sales_representative_id',
            'الموظف المحدد غير مؤهل للعمل كمندوب مبيعات على خط التوزيع.',
        );
    }

    public function validateWarehouse(Warehouse $warehouse): void
    {
        $this->assertWarehouseIdentityCanChange($warehouse);

        $vehicleId = $this->id($warehouse->vehicle_id);

        if ($warehouse->type === 'vehicle') {
            if ($vehicleId === null) {
                $this->fail('vehicle_id', 'مستودع السيارة يجب أن يرتبط بسيارة.');
            }

            $duplicateExists = Warehouse::withoutGlobalScopes()
                ->where('vehicle_id', $vehicleId)
                ->where('id', '!=', $warehouse->getKey() ?? 0)
                ->exists();

            if ($duplicateExists) {
                $this->fail('vehicle_id', 'السيارة المحددة مرتبطة مسبقاً بمستودع آخر.');
            }

            return;
        }

        if ($vehicleId !== null) {
            $this->fail('vehicle_id', 'لا يمكن ربط مستودع رئيسي أو فرعي بسيارة.');
        }
    }

    public function validateSalesInvoice(SalesInvoice $invoice): void
    {
        $customer = $this->customer($invoice->customer_id);
        $route = $this->route($invoice->route_id);
        $warehouse = $this->warehouse($invoice->warehouse_id);

        $this->assertCustomerRoute($customer, $route, requireAssignedRoute: true);
        $this->assertRouteVehicle($route, $invoice->vehicle_id, requireWhenAssigned: true);
        $this->assertWarehouseVehicle($warehouse, $invoice->vehicle_id);
        $this->assertRouteRepresentative(
            $route,
            $invoice->sales_representative_id,
            requireWhenAssigned: true,
        );
        $this->assertEmployeeRole(
            $invoice->sales_representative_id,
            UserRole::SALES_REPRESENTATIVE,
            'sales_representative_id',
            'الموظف المحدد غير مؤهل للعمل كمندوب مبيعات.',
        );
    }

    public function validateSalesReturn(SalesReturn $salesReturn): void
    {
        $invoice = $this->invoice($salesReturn->sales_invoice_id);

        if ($invoice !== null) {
            $this->assertSameContextValue(
                'customer_id',
                $salesReturn->customer_id,
                $invoice->customer_id,
                'عميل المرتجع يجب أن يطابق عميل الفاتورة الأصلية.',
            );
            $this->assertSameContextValue(
                'vehicle_id',
                $salesReturn->vehicle_id,
                $invoice->vehicle_id,
                'سيارة المرتجع يجب أن تطابق سيارة الفاتورة الأصلية.',
            );
            $this->assertSameContextValue(
                'route_id',
                $salesReturn->route_id,
                $invoice->route_id,
                'خط المرتجع يجب أن يطابق خط الفاتورة الأصلية.',
            );
            $this->assertSameContextValue(
                'warehouse_id',
                $salesReturn->warehouse_id,
                $invoice->warehouse_id,
                'مستودع المرتجع يجب أن يطابق مستودع الفاتورة الأصلية.',
            );
            $this->assertSameContextValue(
                'sales_representative_id',
                $salesReturn->sales_representative_id,
                $invoice->sales_representative_id,
                'مندوب المرتجع يجب أن يطابق مندوب الفاتورة الأصلية.',
            );

            return;
        }

        $customer = $this->customer($salesReturn->customer_id);
        $route = $this->route($salesReturn->route_id);
        $warehouse = $this->warehouse($salesReturn->warehouse_id);

        $this->assertCustomerRoute($customer, $route, requireAssignedRoute: true);
        $this->assertRouteVehicle($route, $salesReturn->vehicle_id, requireWhenAssigned: true);
        $this->assertWarehouseVehicle($warehouse, $salesReturn->vehicle_id);
        $this->assertRouteRepresentative(
            $route,
            $salesReturn->sales_representative_id,
            requireWhenAssigned: true,
        );
        $this->assertEmployeeRole(
            $salesReturn->sales_representative_id,
            UserRole::SALES_REPRESENTATIVE,
            'sales_representative_id',
            'الموظف المحدد غير مؤهل للعمل كمندوب مبيعات.',
        );
    }

    public function validateCustomerPayment(CustomerPayment $payment): void
    {
        $invoice = $this->invoice($payment->sales_invoice_id);

        if ($invoice !== null) {
            $this->assertSameContextValue(
                'customer_id',
                $payment->customer_id,
                $invoice->customer_id,
                'عميل التحصيل يجب أن يطابق عميل الفاتورة.',
            );
            $this->assertOptionalSameContextValue(
                'vehicle_id',
                $payment->vehicle_id,
                $invoice->vehicle_id,
                'سيارة التحصيل لا تطابق سيارة الفاتورة.',
            );
            $this->assertOptionalSameContextValue(
                'route_id',
                $payment->route_id,
                $invoice->route_id,
                'خط التحصيل لا يطابق خط الفاتورة.',
            );
            $this->assertOptionalSameContextValue(
                'warehouse_id',
                $payment->warehouse_id,
                $invoice->warehouse_id,
                'مستودع التحصيل لا يطابق مستودع الفاتورة.',
            );
            $this->assertOptionalSameContextValue(
                'sales_representative_id',
                $payment->sales_representative_id,
                $invoice->sales_representative_id,
                'مندوب التحصيل لا يطابق مندوب الفاتورة.',
            );

            return;
        }

        $customer = $this->customer($payment->customer_id);
        $route = $this->route($payment->route_id);
        $warehouse = $this->warehouse($payment->warehouse_id);

        $this->assertCustomerRoute($customer, $route, requireAssignedRoute: true);
        $this->assertRouteVehicle($route, $payment->vehicle_id, requireWhenAssigned: true);

        if ($warehouse !== null) {
            $this->assertWarehouseVehicle($warehouse, $payment->vehicle_id);
        }

        $this->assertRouteRepresentative(
            $route,
            $payment->sales_representative_id,
            requireWhenAssigned: false,
        );
        $this->assertEmployeeRole(
            $payment->sales_representative_id,
            UserRole::SALES_REPRESENTATIVE,
            'sales_representative_id',
            'الموظف المحدد غير مؤهل للعمل كمندوب تحصيل.',
        );
    }

    public function validateVehicleLoad(VehicleLoad $load): void
    {
        $route = $this->route($load->route_id);
        $fromWarehouse = $this->warehouse($load->from_warehouse_id);
        $toWarehouse = $this->warehouse($load->to_warehouse_id);

        if ($fromWarehouse !== null && $fromWarehouse->type === 'vehicle') {
            $this->fail(
                'from_warehouse_id',
                'مستودع مصدر التحميل يجب أن يكون رئيسياً أو فرعياً وليس مستودع سيارة.',
            );
        }

        if ($toWarehouse === null || $toWarehouse->type !== 'vehicle') {
            $this->fail('to_warehouse_id', 'مستودع وجهة التحميل يجب أن يكون مستودع سيارة.');
        }

        if ((int) $toWarehouse->vehicle_id !== (int) $load->vehicle_id) {
            $this->fail('to_warehouse_id', 'مستودع وجهة التحميل لا يتبع السيارة المحددة.');
        }

        if ((int) $load->from_warehouse_id === (int) $load->to_warehouse_id) {
            $this->fail('to_warehouse_id', 'لا يمكن أن يكون مستودع المصدر والوجهة واحداً.');
        }

        $this->assertRouteVehicle($route, $load->vehicle_id, requireWhenAssigned: true);
        $this->assertRouteDriver($route, $load->driver_id);
        $this->assertRouteRepresentative(
            $route,
            $load->sales_representative_id,
            requireWhenAssigned: false,
        );
        $this->assertEmployeeRole(
            $load->driver_id,
            UserRole::DRIVER,
            'driver_id',
            'الموظف المحدد غير مؤهل للعمل كسائق.',
        );
        $this->assertEmployeeRole(
            $load->sales_representative_id,
            UserRole::SALES_REPRESENTATIVE,
            'sales_representative_id',
            'الموظف المحدد غير مؤهل للعمل كمندوب مبيعات.',
        );
    }

    public function validateVehicleExpense(VehicleExpense $expense): void
    {
        $route = $this->route($expense->route_id);
        $warehouse = $this->warehouse($expense->warehouse_id);

        $this->assertWarehouseVehicle($warehouse, $expense->vehicle_id);
        $this->assertRouteVehicle($route, $expense->vehicle_id, requireWhenAssigned: true);
        $this->assertRouteDriver($route, $expense->driver_id);
        $this->assertRouteRepresentative(
            $route,
            $expense->sales_representative_id,
            requireWhenAssigned: false,
        );
        $this->assertEmployeeRole(
            $expense->driver_id,
            UserRole::DRIVER,
            'driver_id',
            'الموظف المحدد غير مؤهل للعمل كسائق.',
        );
        $this->assertEmployeeRole(
            $expense->sales_representative_id,
            UserRole::SALES_REPRESENTATIVE,
            'sales_representative_id',
            'الموظف المحدد غير مؤهل للعمل كمندوب مبيعات.',
        );
    }

    public function validateDailyClosing(DailyClosing $closing): void
    {
        $route = $this->route($closing->route_id);
        $warehouse = $this->warehouse($closing->warehouse_id);

        $this->assertWarehouseVehicle($warehouse, $closing->vehicle_id);
        $this->assertRouteVehicle($route, $closing->vehicle_id, requireWhenAssigned: true);
        $this->assertRouteRepresentative(
            $route,
            $closing->sales_representative_id,
            requireWhenAssigned: false,
        );
        $this->assertEmployeeRole(
            $closing->sales_representative_id,
            UserRole::SALES_REPRESENTATIVE,
            'sales_representative_id',
            'الموظف المحدد غير مؤهل للعمل كمندوب مبيعات.',
        );
    }

    private function assertWarehouseIdentityCanChange(Warehouse $warehouse): void
    {
        if (
            ! $warehouse->exists
            || ! $warehouse->isDirty(['type', 'vehicle_id'])
            || ! $this->warehouseHasHistory((int) $warehouse->getKey())
        ) {
            return;
        }

        $field = $warehouse->isDirty('vehicle_id') ? 'vehicle_id' : 'type';

        $this->fail(
            $field,
            'لا يمكن تغيير نوع المستودع أو السيارة المرتبطة به بعد وجود أرصدة أو عمليات تاريخية. أنشئ مستودعاً جديداً وعطّل المستودع القديم.',
        );
    }

    private function warehouseHasHistory(int $warehouseId): bool
    {
        $singleColumnReferences = [
            'stock_balances' => 'warehouse_id',
            'sales_invoices' => 'warehouse_id',
            'sales_returns' => 'warehouse_id',
            'customer_payments' => 'warehouse_id',
            'vehicle_expenses' => 'warehouse_id',
            'daily_closings' => 'warehouse_id',
        ];

        foreach ($singleColumnReferences as $table => $column) {
            if (
                Schema::hasTable($table)
                && DB::table($table)->where($column, $warehouseId)->exists()
            ) {
                return true;
            }
        }

        foreach ([
            'stock_movements' => ['from_warehouse_id', 'to_warehouse_id'],
            'vehicle_loads' => ['from_warehouse_id', 'to_warehouse_id'],
        ] as $table => [$fromColumn, $toColumn]) {
            if (
                Schema::hasTable($table)
                && DB::table($table)
                    ->where(function ($query) use (
                        $fromColumn,
                        $toColumn,
                        $warehouseId,
                    ): void {
                        $query
                            ->where($fromColumn, $warehouseId)
                            ->orWhere($toColumn, $warehouseId);
                    })
                    ->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    private function assertCustomerRoute(
        ?Customer $customer,
        ?DistributionRoute $route,
        bool $requireAssignedRoute,
    ): void {
        if ($customer === null) {
            return;
        }

        $assignedRouteId = $this->id($customer->route_id);
        $selectedRouteId = $this->id($route?->id);

        if ($requireAssignedRoute && $assignedRouteId !== null && $selectedRouteId === null) {
            $this->fail('route_id', 'يجب استخدام خط التوزيع المعتمد للعميل.');
        }

        if ($assignedRouteId !== null && $selectedRouteId !== $assignedRouteId) {
            $this->fail('route_id', 'خط التوزيع المحدد لا يطابق خط العميل.');
        }

        if (
            $route !== null
            && $customer->area_id !== null
            && (int) $route->area_id !== (int) $customer->area_id
        ) {
            $this->fail('route_id', 'خط التوزيع المحدد لا يتبع منطقة العميل.');
        }
    }

    private function assertRouteVehicle(
        ?DistributionRoute $route,
        mixed $vehicleId,
        bool $requireWhenAssigned,
    ): void {
        if ($route === null) {
            return;
        }

        $routeVehicleId = $this->id($route->vehicle_id);
        $selectedVehicleId = $this->id($vehicleId);

        if ($requireWhenAssigned && $routeVehicleId !== null && $selectedVehicleId === null) {
            $this->fail('vehicle_id', 'يجب استخدام السيارة المعتمدة لخط التوزيع.');
        }

        if ($selectedVehicleId !== null && $selectedVehicleId !== $routeVehicleId) {
            $this->fail('vehicle_id', 'السيارة المحددة لا تتبع خط التوزيع.');
        }
    }

    private function assertWarehouseVehicle(?Warehouse $warehouse, mixed $vehicleId): void
    {
        if ($warehouse === null) {
            return;
        }

        $selectedVehicleId = $this->id($vehicleId);

        if ($selectedVehicleId === null) {
            return;
        }

        if ($warehouse->type !== 'vehicle') {
            $this->fail('warehouse_id', 'العملية المرتبطة بسيارة يجب أن تستخدم مستودع سيارة.');
        }

        if ((int) $warehouse->vehicle_id !== $selectedVehicleId) {
            $this->fail('warehouse_id', 'المستودع المحدد لا يتبع السيارة.');
        }
    }

    private function assertRouteDriver(?DistributionRoute $route, mixed $driverId): void
    {
        $selectedDriverId = $this->id($driverId);

        if ($route === null || $selectedDriverId === null) {
            return;
        }

        if ($selectedDriverId !== $this->id($route->driver_id)) {
            $this->fail('driver_id', 'السائق المحدد ليس السائق المعتمد لخط التوزيع.');
        }
    }

    private function assertRouteRepresentative(
        ?DistributionRoute $route,
        mixed $representativeId,
        bool $requireWhenAssigned,
    ): void {
        if ($route === null) {
            return;
        }

        $routeRepresentativeId = $this->id($route->sales_representative_id);
        $selectedRepresentativeId = $this->id($representativeId);

        if ($requireWhenAssigned && $routeRepresentativeId !== null && $selectedRepresentativeId === null) {
            $this->fail(
                'sales_representative_id',
                'يجب استخدام مندوب المبيعات المعتمد لخط التوزيع.',
            );
        }

        if ($selectedRepresentativeId !== null && $selectedRepresentativeId !== $routeRepresentativeId) {
            $this->fail(
                'sales_representative_id',
                'مندوب المبيعات المحدد ليس المندوب المعتمد لخط التوزيع.',
            );
        }
    }

    private function assertEmployeeRole(
        mixed $employeeId,
        UserRole $role,
        string $field,
        string $message,
    ): void {
        $employeeId = $this->id($employeeId);

        if ($employeeId === null) {
            return;
        }

        $employee = Employee::withoutGlobalScopes()
            ->with('user.roles')
            ->find($employeeId);

        if (
            $employee === null
            || $employee->status !== 'active'
            || ! $employee->canFulfillOperationalRole($role)
        ) {
            $this->fail($field, $message);
        }
    }

    private function assertSameContextValue(
        string $field,
        mixed $actual,
        mixed $expected,
        string $message,
    ): void {
        if ($this->id($actual) !== $this->id($expected)) {
            $this->fail($field, $message);
        }
    }

    private function assertOptionalSameContextValue(
        string $field,
        mixed $actual,
        mixed $expected,
        string $message,
    ): void {
        if ($this->id($actual) === null) {
            return;
        }

        $this->assertSameContextValue($field, $actual, $expected, $message);
    }

    private function customer(mixed $id): ?Customer
    {
        $id = $this->id($id);

        return $id === null ? null : Customer::withoutGlobalScopes()->find($id);
    }

    private function route(mixed $id): ?DistributionRoute
    {
        $id = $this->id($id);

        return $id === null ? null : DistributionRoute::withoutGlobalScopes()->find($id);
    }

    private function warehouse(mixed $id): ?Warehouse
    {
        $id = $this->id($id);

        return $id === null ? null : Warehouse::withoutGlobalScopes()->find($id);
    }

    private function invoice(mixed $id): ?SalesInvoice
    {
        $id = $this->id($id);

        return $id === null ? null : SalesInvoice::withoutGlobalScopes()->find($id);
    }

    private function id(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }
}
