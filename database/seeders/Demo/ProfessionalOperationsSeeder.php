<?php

namespace Database\Seeders\Demo;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyClosing;
use App\Models\DistributionRoute;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\VehicleExpense;
use App\Models\VehicleLoad;
use App\Models\Warehouse;
use App\Services\Distribution\DailyClosingService;
use App\Services\Distribution\VehicleExpenseService;
use App\Services\Distribution\VehicleLoadService;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Sales\CustomerPaymentService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesReturnService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProfessionalOperationsSeeder extends Seeder
{
    /** @var array<string, int> */
    private array $documentCounters = [];

    /** @var array<string, array{batch: string, expiry: ?string}> */
    private array $stockLots = [];

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@demo.local')->firstOrFail();
        Auth::login($admin);

        try {
            $this->stockLots = $this->buildStockLots();
            $this->seedOpeningInventory();
            $this->seedVehicleLoads();
            $invoices = $this->seedSalesInvoices();
            $this->seedSalesReturns($invoices);
            $this->seedCustomerPayments($invoices);
            $this->seedVehicleExpenses();
            $this->seedDailyClosings();
            $this->clearMobileSyncRuntimeState();
        } finally {
            Auth::logout();
        }
    }

    /** @return array<string, array{batch: string, expiry: ?string}> */
    private function buildStockLots(): array
    {
        return [
            'FD-001' => ['batch' => 'D-2607-A', 'expiry' => today()->addDays(14)->toDateString()],
            'FD-002' => ['batch' => 'D-2607-B', 'expiry' => today()->addDays(28)->toDateString()],
            'FD-003' => ['batch' => 'D-2607-C', 'expiry' => today()->addDays(90)->toDateString()],
            'FD-004' => ['batch' => 'B-2606-A', 'expiry' => today()->addDays(120)->toDateString()],
            'FD-005' => ['batch' => 'B-2606-B', 'expiry' => today()->addDays(180)->toDateString()],
            'FD-006' => ['batch' => 'B-2606-C', 'expiry' => today()->addDays(150)->toDateString()],
            'FD-007' => ['batch' => 'C-2605-A', 'expiry' => today()->addDays(210)->toDateString()],
            'FD-008' => ['batch' => 'C-2605-B', 'expiry' => today()->addDays(190)->toDateString()],
            'FD-009' => ['batch' => 'R-2604-A', 'expiry' => today()->addDays(240)->toDateString()],
            'FD-010' => ['batch' => 'R-2604-B', 'expiry' => today()->addDays(240)->toDateString()],
            'FD-011' => ['batch' => 'R-2604-C', 'expiry' => today()->addDays(180)->toDateString()],
            'FD-012' => ['batch' => 'S-2607-A', 'expiry' => today()->addDays(7)->toDateString()],
            'FD-013' => ['batch' => 'S-2606-B', 'expiry' => today()->addDays(60)->toDateString()],
            'FD-014' => ['batch' => 'H-2606-A', 'expiry' => null],
            'FD-015' => ['batch' => 'H-2606-B', 'expiry' => null],
        ];
    }

    private function seedOpeningInventory(): void
    {
        $inventory = app(InventoryMovementService::class);
        $main = Warehouse::query()->where('code', 'WH-MAIN')->firstOrFail();
        $cold = Warehouse::query()->where('code', 'WH-COLD')->firstOrFail();
        $reserve = Warehouse::query()->where('code', 'WH-RESERVE')->firstOrFail();
        $openingDate = today()->subDays(45);

        foreach (Product::query()->orderBy('id')->get() as $product) {
            $lot = $this->stockLots[$product->sku];
            $quantity = match ($product->sku) {
                'FD-004', 'FD-005', 'FD-006' => 420,
                'FD-013' => 260,
                default => 600,
            };

            $inventory->addStock(
                warehouse: $main,
                product: $product,
                quantity: $quantity,
                batchNumber: $lot['batch'],
                expiryDate: $lot['expiry'],
                unitCost: $product->purchase_price,
                movementType: 'opening_balance',
                notes: 'رصيد افتتاحي للبيئة التجريبية الاحترافية.',
                movementDate: $openingDate,
            );
        }

        $this->addSpecialRiskBalance(
            $inventory,
            $reserve,
            'FD-007',
            75,
            'C-EXPIRED-01',
            today()->subDays(5)->toDateString(),
            'دفعة منتهية لاختبار تقرير مخاطر الصلاحية.',
            $openingDate,
        );
        $this->addSpecialRiskBalance(
            $inventory,
            $cold,
            'FD-004',
            40,
            'B-MISSING-EXP',
            null,
            'دفعة دون تاريخ صلاحية لاختبار جودة البيانات.',
            $openingDate,
        );
        $this->addSpecialRiskBalance(
            $inventory,
            $cold,
            'FD-001',
            180,
            'D-SAFE-01',
            today()->addDays(75)->toDateString(),
            'دفعة ألبان احتياطية آمنة.',
            $openingDate,
        );
    }

    private function addSpecialRiskBalance(
        InventoryMovementService $inventory,
        Warehouse $warehouse,
        string $sku,
        float $quantity,
        string $batch,
        ?string $expiry,
        string $notes,
        Carbon $date,
    ): void {
        $product = Product::query()->where('sku', $sku)->firstOrFail();

        $inventory->addStock(
            warehouse: $warehouse,
            product: $product,
            quantity: $quantity,
            batchNumber: $batch,
            expiryDate: $expiry,
            unitCost: $product->purchase_price,
            movementType: 'opening_balance',
            notes: $notes,
            movementDate: $date,
        );
    }

    private function seedVehicleLoads(): void
    {
        $loadPlans = [
            ['route' => 'RT-DAM-C', 'days' => -40, 'items' => ['FD-001' => 100, 'FD-002' => 70, 'FD-004' => 55, 'FD-005' => 90, 'FD-007' => 80, 'FD-009' => 90, 'FD-012' => 75, 'FD-014' => 60]],
            ['route' => 'RT-DAM-S', 'days' => -40, 'items' => ['FD-001' => 85, 'FD-003' => 55, 'FD-005' => 80, 'FD-006' => 50, 'FD-008' => 75, 'FD-010' => 90, 'FD-013' => 45, 'FD-015' => 65]],
            ['route' => 'RT-RIF-E', 'days' => -40, 'items' => ['FD-002' => 70, 'FD-004' => 50, 'FD-005' => 100, 'FD-007' => 90, 'FD-009' => 100, 'FD-011' => 110, 'FD-012' => 70, 'FD-014' => 60]],

            ['route' => 'RT-DAM-C', 'days' => -16, 'items' => ['FD-001' => 55, 'FD-004' => 30, 'FD-005' => 45, 'FD-009' => 40, 'FD-012' => 35]],
            ['route' => 'RT-DAM-S', 'days' => -14, 'items' => ['FD-003' => 30, 'FD-006' => 28, 'FD-008' => 45, 'FD-010' => 45, 'FD-013' => 25]],
            ['route' => 'RT-RIF-E', 'days' => -13, 'items' => ['FD-002' => 35, 'FD-005' => 50, 'FD-007' => 45, 'FD-011' => 55, 'FD-014' => 30]],

            ['route' => 'RT-DAM-C', 'days' => 0, 'items' => ['FD-001' => 30, 'FD-002' => 20, 'FD-004' => 18, 'FD-012' => 20]],
            ['route' => 'RT-DAM-S', 'days' => 0, 'items' => ['FD-003' => 18, 'FD-005' => 25, 'FD-008' => 20, 'FD-015' => 24]],
            ['route' => 'RT-RIF-E', 'days' => 0, 'items' => ['FD-004' => 16, 'FD-007' => 22, 'FD-009' => 25, 'FD-011' => 30]],
        ];

        foreach ($loadPlans as $plan) {
            $this->createApprovedLoad(
                routeCode: $plan['route'],
                date: today()->addDays($plan['days']),
                items: $plan['items'],
            );
        }

        $route = DistributionRoute::query()->where('code', 'RT-DAM-C')->firstOrFail();
        $main = Warehouse::query()->where('code', 'WH-MAIN')->firstOrFail();
        $vehicleWarehouse = Warehouse::query()->where('vehicle_id', $route->vehicle_id)->firstOrFail();

        $draft = VehicleLoad::query()->create([
            'load_number' => $this->documentNumber('VLD', today()),
            'vehicle_id' => $route->vehicle_id,
            'route_id' => $route->id,
            'driver_id' => $route->driver_id,
            'sales_representative_id' => $route->sales_representative_id,
            'from_warehouse_id' => $main->id,
            'to_warehouse_id' => $vehicleWarehouse->id,
            'load_date' => today(),
            'status' => 'draft',
            'notes' => 'تحميل إضافي بانتظار المراجعة.',
        ]);

        $product = Product::query()->where('sku', 'FD-005')->firstOrFail();
        $lot = $this->stockLots['FD-005'];
        $draft->items()->create([
            'product_id' => $product->id,
            'batch_number' => $lot['batch'],
            'expiry_date' => $lot['expiry'],
            'quantity' => 12,
            'unit_cost' => $product->purchase_price,
        ]);
    }

    /** @param array<string, float|int> $items */
    private function createApprovedLoad(string $routeCode, Carbon $date, array $items): VehicleLoad
    {
        $route = DistributionRoute::query()->where('code', $routeCode)->firstOrFail();
        $main = Warehouse::query()->where('code', 'WH-MAIN')->firstOrFail();
        $vehicleWarehouse = Warehouse::query()->where('vehicle_id', $route->vehicle_id)->firstOrFail();

        $load = VehicleLoad::query()->create([
            'load_number' => $this->documentNumber('VLD', $date),
            'vehicle_id' => $route->vehicle_id,
            'route_id' => $route->id,
            'driver_id' => $route->driver_id,
            'sales_representative_id' => $route->sales_representative_id,
            'from_warehouse_id' => $main->id,
            'to_warehouse_id' => $vehicleWarehouse->id,
            'load_date' => $date,
            'status' => 'draft',
            'notes' => 'تحميل تجريبي مرتبط بخطة توزيع فعلية.',
        ]);

        foreach ($items as $sku => $quantity) {
            $product = Product::query()->where('sku', $sku)->firstOrFail();
            $lot = $this->stockLots[$sku];

            $load->items()->create([
                'product_id' => $product->id,
                'batch_number' => $lot['batch'],
                'expiry_date' => $lot['expiry'],
                'quantity' => $quantity,
                'unit_cost' => $product->purchase_price,
            ]);
        }

        return app(VehicleLoadService::class)->approve($load);
    }

    /** @return array<string, SalesInvoice> */
    private function seedSalesInvoices(): array
    {
        $plans = [
            ['key' => 'central_overdue', 'customer' => 'CUS-001', 'days' => -35, 'type' => 'credit', 'paid' => 0, 'items' => ['FD-004' => 6, 'FD-005' => 12, 'FD-009' => 10]],
            ['key' => 'central_monthly', 'customer' => 'CUS-002', 'days' => -22, 'type' => 'credit', 'paid' => 0, 'items' => ['FD-001' => 12, 'FD-002' => 8, 'FD-007' => 15]],
            ['key' => 'central_cash', 'customer' => 'CUS-003', 'days' => -9, 'type' => 'cash', 'paid' => 0, 'items' => ['FD-005' => 10, 'FD-012' => 8, 'FD-014' => 5]],
            ['key' => 'central_partial', 'customer' => 'CUS-004', 'days' => -6, 'type' => 'partial', 'paid' => 120000, 'items' => ['FD-001' => 10, 'FD-004' => 4, 'FD-009' => 8]],
            ['key' => 'central_today', 'customer' => 'CUS-005', 'days' => 0, 'type' => 'partial', 'paid' => 90000, 'items' => ['FD-002' => 6, 'FD-005' => 8, 'FD-012' => 6]],

            ['key' => 'south_overdue', 'customer' => 'CUS-006', 'days' => -28, 'type' => 'credit', 'paid' => 0, 'items' => ['FD-003' => 8, 'FD-006' => 5, 'FD-010' => 12]],
            ['key' => 'south_cash', 'customer' => 'CUS-007', 'days' => -8, 'type' => 'cash', 'paid' => 0, 'items' => ['FD-005' => 10, 'FD-008' => 12, 'FD-015' => 6]],
            ['key' => 'south_partial', 'customer' => 'CUS-008', 'days' => -4, 'type' => 'partial', 'paid' => 85000, 'items' => ['FD-001' => 8, 'FD-010' => 10, 'FD-013' => 3]],
            ['key' => 'south_today', 'customer' => 'CUS-009', 'days' => 0, 'type' => 'credit', 'paid' => 0, 'items' => ['FD-003' => 5, 'FD-006' => 4, 'FD-008' => 10]],
            ['key' => 'south_cancelled', 'customer' => 'CUS-010', 'days' => -3, 'type' => 'cash', 'paid' => 0, 'items' => ['FD-005' => 4, 'FD-015' => 3], 'cancel' => true],

            ['key' => 'rif_overdue', 'customer' => 'CUS-011', 'days' => -38, 'type' => 'credit', 'paid' => 0, 'items' => ['FD-004' => 7, 'FD-007' => 16, 'FD-011' => 18]],
            ['key' => 'rif_weekly', 'customer' => 'CUS-012', 'days' => -17, 'type' => 'partial', 'paid' => 100000, 'items' => ['FD-002' => 7, 'FD-005' => 12, 'FD-009' => 12]],
            ['key' => 'rif_cash', 'customer' => 'CUS-013', 'days' => -6, 'type' => 'cash', 'paid' => 0, 'items' => ['FD-005' => 12, 'FD-011' => 15, 'FD-012' => 10]],
            ['key' => 'rif_today', 'customer' => 'CUS-014', 'days' => 0, 'type' => 'partial', 'paid' => 110000, 'items' => ['FD-004' => 4, 'FD-007' => 10, 'FD-014' => 6]],
            ['key' => 'rif_recent', 'customer' => 'CUS-015', 'days' => -5, 'type' => 'credit', 'paid' => 0, 'items' => ['FD-002' => 8, 'FD-009' => 14, 'FD-011' => 20]],
        ];

        $invoices = [];

        foreach ($plans as $plan) {
            $invoice = $this->createInvoice(
                customerCode: $plan['customer'],
                date: today()->addDays($plan['days']),
                paymentType: $plan['type'],
                paidAmount: (float) $plan['paid'],
                items: $plan['items'],
            );

            if ($plan['cancel'] ?? false) {
                $invoice = app(SalesInvoiceService::class)->cancel($invoice);
            }

            $invoices[$plan['key']] = $invoice;
        }

        $this->createDraftInvoice('CUS-020', today(), ['FD-005' => 5, 'FD-015' => 4]);

        return $invoices;
    }

    /** @param array<string, int|float> $items */
    private function createInvoice(
        string $customerCode,
        Carbon $date,
        string $paymentType,
        float $paidAmount,
        array $items,
    ): SalesInvoice {
        $customer = Customer::query()->where('code', $customerCode)->firstOrFail();
        $route = $customer->route()->firstOrFail();
        $warehouse = Warehouse::query()->where('vehicle_id', $route->vehicle_id)->firstOrFail();

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => $this->documentNumber('INV', $date),
            'customer_id' => $customer->id,
            'vehicle_id' => $route->vehicle_id,
            'route_id' => $route->id,
            'warehouse_id' => $warehouse->id,
            'sales_representative_id' => $route->sales_representative_id,
            'invoice_date' => $date,
            'status' => 'draft',
            'payment_type' => $paymentType,
            'paid_amount' => $paidAmount,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'notes' => 'فاتورة تجريبية مهنية لاختبار الدورة المالية والمخزنية.',
        ]);

        foreach ($items as $sku => $quantity) {
            $product = Product::query()->where('sku', $sku)->firstOrFail();
            $lot = $this->stockLots[$sku];

            $invoice->items()->create([
                'product_id' => $product->id,
                'batch_number' => $lot['batch'],
                'expiry_date' => $lot['expiry'],
                'quantity' => $quantity,
                'unit_price' => $product->sale_price,
                'discount_amount' => 0,
            ]);
        }

        return app(SalesInvoiceService::class)->confirm($invoice);
    }

    /** @param array<string, int|float> $items */
    private function createDraftInvoice(string $customerCode, Carbon $date, array $items): SalesInvoice
    {
        $customer = Customer::query()->where('code', $customerCode)->firstOrFail();
        $route = $customer->route()->firstOrFail();
        $warehouse = Warehouse::query()->where('vehicle_id', $route->vehicle_id)->firstOrFail();

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => $this->documentNumber('INV', $date),
            'customer_id' => $customer->id,
            'vehicle_id' => $route->vehicle_id,
            'route_id' => $route->id,
            'warehouse_id' => $warehouse->id,
            'sales_representative_id' => $route->sales_representative_id,
            'invoice_date' => $date,
            'status' => 'draft',
            'payment_type' => 'credit',
            'paid_amount' => 0,
            'notes' => 'مسودة ميدانية بانتظار المراجعة والاعتماد.',
        ]);

        foreach ($items as $sku => $quantity) {
            $product = Product::query()->where('sku', $sku)->firstOrFail();
            $lot = $this->stockLots[$sku];
            $invoice->items()->create([
                'product_id' => $product->id,
                'batch_number' => $lot['batch'],
                'expiry_date' => $lot['expiry'],
                'quantity' => $quantity,
                'unit_price' => $product->sale_price,
                'discount_amount' => 0,
            ]);
        }

        return $invoice->refresh();
    }

    /** @param array<string, SalesInvoice> $invoices */
    private function seedSalesReturns(array $invoices): void
    {
        $this->createReturn($invoices['central_overdue'], today()->subDays(30), 'FD-004', 1, 'تلف في العبوة الخارجية');
        $this->createReturn($invoices['south_overdue'], today()->subDays(20), 'FD-003', 1, 'استبدال قطعة غير مطابقة');
        $this->createReturn($invoices['rif_weekly'], today()->subDays(10), 'FD-005', 2, 'مرتجع كمية زائدة');

        $invoice = $invoices['central_cash'];
        $draft = SalesReturn::query()->create([
            'return_number' => $this->documentNumber('SRT', today()),
            'customer_id' => $invoice->customer_id,
            'sales_invoice_id' => $invoice->id,
            'vehicle_id' => $invoice->vehicle_id,
            'route_id' => $invoice->route_id,
            'warehouse_id' => $invoice->warehouse_id,
            'sales_representative_id' => $invoice->sales_representative_id,
            'return_date' => today(),
            'status' => 'draft',
            'return_reason' => 'مسودة مرتجع بانتظار الفحص',
        ]);
        $sourceItem = $invoice->items()->firstOrFail();
        $draft->items()->create([
            'product_id' => $sourceItem->product_id,
            'batch_number' => $sourceItem->batch_number,
            'expiry_date' => $sourceItem->expiry_date,
            'quantity' => 1,
            'unit_price' => $sourceItem->unit_price,
        ]);
    }

    private function createReturn(
        SalesInvoice $invoice,
        Carbon $date,
        string $sku,
        float $quantity,
        string $reason,
    ): SalesReturn {
        $product = Product::query()->where('sku', $sku)->firstOrFail();
        $sourceItem = $invoice->items()->where('product_id', $product->id)->firstOrFail();

        $return = SalesReturn::query()->create([
            'return_number' => $this->documentNumber('SRT', $date),
            'customer_id' => $invoice->customer_id,
            'sales_invoice_id' => $invoice->id,
            'vehicle_id' => $invoice->vehicle_id,
            'route_id' => $invoice->route_id,
            'warehouse_id' => $invoice->warehouse_id,
            'sales_representative_id' => $invoice->sales_representative_id,
            'return_date' => $date,
            'status' => 'draft',
            'return_reason' => $reason,
            'discount_amount' => 0,
            'notes' => 'مرتجع مرتبط بالفاتورة الأصلية.',
        ]);

        $return->items()->create([
            'product_id' => $product->id,
            'batch_number' => $sourceItem->batch_number,
            'expiry_date' => $sourceItem->expiry_date,
            'quantity' => $quantity,
            'unit_price' => $sourceItem->unit_price,
        ]);

        return app(SalesReturnService::class)->confirm($return);
    }

    /** @param array<string, SalesInvoice> $invoices */
    private function seedCustomerPayments(array $invoices): void
    {
        $plans = [
            [$invoices['central_overdue'], -10, 200000, 'cash'],
            [$invoices['central_monthly'], -5, 100000, 'bank_transfer'],
            [$invoices['central_partial'], -2, 80000, 'cash'],
            [$invoices['south_overdue'], -5, 150000, 'cheque'],
            [$invoices['south_partial'], -1, 60000, 'cash'],
            [$invoices['rif_overdue'], -12, 200000, 'bank_transfer'],
            [$invoices['rif_weekly'], -3, 90000, 'cash'],
            [$invoices['rif_recent'], 0, 50000, 'other'],
        ];

        foreach ($plans as [$invoice, $days, $amount, $method]) {
            $this->createPayment(
                invoice: $invoice,
                date: today()->addDays($days),
                amount: $amount,
                method: $method,
            );
        }

        $customer = Customer::query()->where('code', 'CUS-005')->firstOrFail();
        $route = $customer->route()->firstOrFail();
        $warehouse = Warehouse::query()->where('vehicle_id', $route->vehicle_id)->firstOrFail();

        $standalone = CustomerPayment::query()->create([
            'payment_number' => $this->documentNumber('PAY', today()),
            'customer_id' => $customer->id,
            'sales_invoice_id' => null,
            'vehicle_id' => $route->vehicle_id,
            'route_id' => $route->id,
            'warehouse_id' => $warehouse->id,
            'sales_representative_id' => $route->sales_representative_id,
            'payment_date' => today(),
            'payment_method' => 'cash',
            'status' => 'draft',
            'amount' => 50000,
            'reference_number' => 'ADVANCE-DEMO',
            'notes' => 'دفعة على الحساب غير مرتبطة بفاتورة محددة.',
        ]);
        app(CustomerPaymentService::class)->confirm($standalone);

        $draftInvoice = $invoices['south_today'];
        CustomerPayment::query()->create([
            'payment_number' => $this->documentNumber('PAY', today()),
            'customer_id' => $draftInvoice->customer_id,
            'sales_invoice_id' => $draftInvoice->id,
            'payment_date' => today(),
            'payment_method' => 'cash',
            'status' => 'draft',
            'amount' => 25000,
            'notes' => 'مسودة تحصيل بانتظار الاعتماد.',
        ]);
    }

    private function createPayment(
        SalesInvoice $invoice,
        Carbon $date,
        float $amount,
        string $method,
    ): CustomerPayment {
        $payment = CustomerPayment::query()->create([
            'payment_number' => $this->documentNumber('PAY', $date),
            'customer_id' => $invoice->customer_id,
            'sales_invoice_id' => $invoice->id,
            'vehicle_id' => $invoice->vehicle_id,
            'route_id' => $invoice->route_id,
            'warehouse_id' => $invoice->warehouse_id,
            'sales_representative_id' => $invoice->sales_representative_id,
            'payment_date' => $date,
            'payment_method' => $method,
            'status' => 'draft',
            'amount' => $amount,
            'reference_number' => strtoupper($method).'-'.$invoice->id,
            'notes' => 'تحصيل تجريبي مرتبط بفاتورة معتمدة.',
        ]);

        return app(CustomerPaymentService::class)->confirm($payment);
    }

    private function seedVehicleExpenses(): void
    {
        $plans = [
            ['route' => 'RT-DAM-C', 'days' => -10, 'type' => 'fuel', 'amount' => 150000, 'method' => 'cash', 'status' => 'approved'],
            ['route' => 'RT-DAM-C', 'days' => 0, 'type' => 'fuel', 'amount' => 180000, 'method' => 'cash', 'status' => 'approved'],
            ['route' => 'RT-DAM-S', 'days' => -4, 'type' => 'maintenance', 'amount' => 250000, 'method' => 'bank_transfer', 'status' => 'approved'],
            ['route' => 'RT-DAM-S', 'days' => 0, 'type' => 'parking', 'amount' => 20000, 'method' => 'cash', 'status' => 'pending'],
            ['route' => 'RT-RIF-E', 'days' => -12, 'type' => 'fuel', 'amount' => 220000, 'method' => 'cash', 'status' => 'approved'],
            ['route' => 'RT-RIF-E', 'days' => 0, 'type' => 'tolls', 'amount' => 35000, 'method' => 'cash', 'status' => 'approved'],
            ['route' => 'RT-DAM-C', 'days' => -2, 'type' => 'other', 'amount' => 45000, 'method' => 'cash', 'status' => 'rejected'],
        ];

        foreach ($plans as $plan) {
            $route = DistributionRoute::query()->where('code', $plan['route'])->firstOrFail();
            $warehouse = Warehouse::query()->where('vehicle_id', $route->vehicle_id)->firstOrFail();

            $expense = VehicleExpense::query()->create([
                'vehicle_id' => $route->vehicle_id,
                'warehouse_id' => $warehouse->id,
                'route_id' => $route->id,
                'driver_id' => $route->driver_id,
                'sales_representative_id' => $route->sales_representative_id,
                'expense_date' => today()->addDays($plan['days']),
                'expense_type' => $plan['type'],
                'amount' => $plan['amount'],
                'payment_method' => $plan['method'],
                'status' => 'pending',
                'notes' => 'مصروف مركبة تجريبي موثق ضمن مسار العمل.',
            ]);

            if ($plan['status'] === 'approved') {
                app(VehicleExpenseService::class)->approve($expense);
            } elseif ($plan['status'] === 'rejected') {
                app(VehicleExpenseService::class)->reject(
                    $expense,
                    'المرفق غير واضح وتم رفض المصروف لأغراض العرض التجريبي.',
                );
            }
        }
    }

    private function seedDailyClosings(): void
    {
        $plans = [
            ['route' => 'RT-DAM-C', 'days' => -10, 'variance' => 5000, 'stock_variance' => 0],
            ['route' => 'RT-DAM-S', 'days' => -4, 'variance' => 0, 'stock_variance' => 1],
            ['route' => 'RT-RIF-E', 'days' => -3, 'variance' => -3000, 'stock_variance' => 0],
            ['route' => 'RT-DAM-C', 'days' => -1, 'variance' => 0, 'stock_variance' => 0],
            ['route' => 'RT-RIF-E', 'days' => -2, 'variance' => 2000, 'stock_variance' => 0],
        ];

        foreach ($plans as $plan) {
            $this->createConfirmedClosing(
                routeCode: $plan['route'],
                date: today()->addDays($plan['days']),
                cashVariance: (float) $plan['variance'],
                stockVariance: (float) $plan['stock_variance'],
            );
        }

        $route = DistributionRoute::query()->where('code', 'RT-DAM-S')->firstOrFail();
        $warehouse = Warehouse::query()->where('vehicle_id', $route->vehicle_id)->firstOrFail();
        $draft = DailyClosing::query()->create([
            'closing_number' => $this->documentNumber('DCL', today()),
            'closing_date' => today(),
            'vehicle_id' => $route->vehicle_id,
            'route_id' => $route->id,
            'warehouse_id' => $warehouse->id,
            'driver_id' => $route->driver_id,
            'sales_representative_id' => $route->sales_representative_id,
            'status' => 'draft',
            'actual_cash_amount' => 0,
            'notes' => 'إغلاق اليوم بانتظار إدخال الجرد الفعلي والاعتماد.',
        ]);
        app(DailyClosingService::class)->refreshTotals($draft);
    }

    private function createConfirmedClosing(
        string $routeCode,
        Carbon $date,
        float $cashVariance,
        float $stockVariance,
    ): DailyClosing {
        $route = DistributionRoute::query()->where('code', $routeCode)->firstOrFail();
        $warehouse = Warehouse::query()->where('vehicle_id', $route->vehicle_id)->firstOrFail();

        $closing = DailyClosing::query()->create([
            'closing_number' => $this->documentNumber('DCL', $date),
            'closing_date' => $date,
            'vehicle_id' => $route->vehicle_id,
            'route_id' => $route->id,
            'warehouse_id' => $warehouse->id,
            'driver_id' => $route->driver_id,
            'sales_representative_id' => $route->sales_representative_id,
            'status' => 'draft',
            'actual_cash_amount' => 0,
            'notes' => 'إغلاق تجريبي موثق لليوم والخط والسيارة.',
        ]);

        $service = app(DailyClosingService::class);
        $closing = $service->refreshTotals($closing);
        $closing->forceFill([
            'actual_cash_amount' => (float) $closing->expected_cash_amount + $cashVariance,
        ])->save();

        $firstItem = true;
        foreach ($closing->items()->orderBy('id')->get() as $item) {
            $actual = (float) $item->expected_quantity;
            $applyStockVariance = $firstItem && $stockVariance !== 0.0;

            if ($applyStockVariance) {
                $actual += $stockVariance;
            }

            $firstItem = false;

            $item->forceFill([
                'actual_quantity' => $actual,
                'notes' => $applyStockVariance
                    ? 'فرق جرد تجريبي موثق.'
                    : null,
            ])->save();
        }

        return $service->confirm($closing);
    }

    private function clearMobileSyncRuntimeState(): void
    {
        foreach ([
            'mobile_sync_push_operations',
            'mobile_sync_push_batches',
            'mobile_sync_checkpoints',
            'mobile_sync_states',
            'personal_access_tokens',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    private function documentNumber(string $prefix, Carbon $date): string
    {
        $key = $prefix.'|'.$date->toDateString();
        $number = ($this->documentCounters[$key] ?? 0) + 1;
        $this->documentCounters[$key] = $number;

        return sprintf(
            '%s-%s-%05d',
            $prefix,
            $date->format('Ymd'),
            $number,
        );
    }
}
