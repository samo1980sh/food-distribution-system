<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Vehicle;
use App\Models\VehicleLoad;
use App\Models\VehicleLoadItem;
use App\Models\Warehouse;
use App\Services\Distribution\VehicleLoadService;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesReturnService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class OperationalInventoryImpactTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syncStockMovementSequenceForToday();
    }

    public function test_vehicle_load_approval_and_cancellation_move_stock_between_warehouses(): void
    {
        $suffix = uniqid();

        $vehicle = Vehicle::query()->create([
            'code' => 'V-INV-OPS-'.$suffix,
            'plate_number' => 'OPS-'.$suffix,
            'status' => 'active',
        ]);

        $sourceWarehouse = Warehouse::query()->create([
            'code' => 'W-OPS-SRC-'.$suffix,
            'name' => 'Ops Source '.$suffix,
            'type' => 'main',
            'status' => 'active',
        ]);

        $vehicleWarehouse = Warehouse::query()->create([
            'code' => 'W-OPS-VEH-'.$suffix,
            'name' => 'Ops Vehicle '.$suffix,
            'type' => 'vehicle',
            'vehicle_id' => $vehicle->id,
            'status' => 'active',
        ]);

        $product = $this->createProduct($suffix);

        app(InventoryMovementService::class)->addStock(
            warehouse: $sourceWarehouse,
            product: $product,
            quantity: 10,
            movementType: 'opening_balance',
        );

        $vehicleLoad = VehicleLoad::query()->create([
            'load_number' => 'VLD-OPS-'.$suffix,
            'vehicle_id' => $vehicle->id,
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $vehicleWarehouse->id,
            'load_date' => now()->toDateString(),
            'status' => 'draft',
            'total_quantity' => 4,
            'total_cost' => 0,
        ]);

        VehicleLoadItem::query()->create([
            'vehicle_load_id' => $vehicleLoad->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_cost' => 0,
            'total_cost' => 0,
        ]);

        app(VehicleLoadService::class)->approve($vehicleLoad);

        $this->assertEqualsWithDelta(6, $this->balanceQuantity($sourceWarehouse, $product), 0.0001);
        $this->assertEqualsWithDelta(4, $this->balanceQuantity($vehicleWarehouse, $product), 0.0001);
        $this->assertEqualsWithDelta(12, $this->balanceAverageCost($sourceWarehouse, $product), 0.000001);
        $this->assertEqualsWithDelta(12, $this->balanceAverageCost($vehicleWarehouse, $product), 0.000001);
        $this->assertEqualsWithDelta(12, (float) $vehicleLoad->items()->firstOrFail()->unit_cost, 0.000001);
        $this->assertEqualsWithDelta(48, (float) $vehicleLoad->refresh()->total_cost, 0.001);

        app(VehicleLoadService::class)->cancel($vehicleLoad->refresh());

        $this->assertEqualsWithDelta(10, $this->balanceQuantity($sourceWarehouse, $product), 0.0001);
        $this->assertEqualsWithDelta(0, $this->balanceQuantity($vehicleWarehouse, $product), 0.0001);
        $this->assertEqualsWithDelta(12, $this->balanceAverageCost($sourceWarehouse, $product), 0.000001);
    }

    public function test_sales_invoice_confirmation_and_cancellation_updates_stock_balance(): void
    {
        $suffix = uniqid();

        $warehouse = $this->createWarehouse('W-INV-SALE-'.$suffix, 'Ops Sale Warehouse '.$suffix);
        $customer = $this->createCustomer($suffix);
        $product = $this->createProduct($suffix);

        app(InventoryMovementService::class)->addStock(
            warehouse: $warehouse,
            product: $product,
            quantity: 10,
            movementType: 'opening_balance',
        );

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-OPS-'.$suffix,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
            'payment_type' => 'credit',
            'subtotal' => 60,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 60,
            'paid_amount' => 0,
            'invoice_cash_amount' => 0,
            'remaining_amount' => 60,
        ]);

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 20,
            'discount_amount' => 0,
            'line_total' => 60,
        ]);

        app(SalesInvoiceService::class)->confirm($invoice);

        $this->assertEqualsWithDelta(7, $this->balanceQuantity($warehouse, $product), 0.0001);
        $invoiceItem = $invoice->items()->firstOrFail();
        $this->assertEqualsWithDelta(12, (float) $invoiceItem->unit_cost, 0.000001);
        $this->assertEqualsWithDelta(36, (float) $invoiceItem->total_cost, 0.001);

        app(SalesInvoiceService::class)->cancel($invoice->refresh());

        $this->assertEqualsWithDelta(10, $this->balanceQuantity($warehouse, $product), 0.0001);
        $this->assertEqualsWithDelta(12, $this->balanceAverageCost($warehouse, $product), 0.000001);
    }

    public function test_sales_return_confirmation_and_cancellation_updates_stock_balance(): void
    {
        $suffix = uniqid();

        $warehouse = $this->createWarehouse('W-INV-RET-'.$suffix, 'Ops Return Warehouse '.$suffix);
        $customer = $this->createCustomer($suffix);
        $product = $this->createProduct($suffix);

        app(InventoryMovementService::class)->addStock(
            warehouse: $warehouse,
            product: $product,
            quantity: 10,
            movementType: 'opening_balance',
        );

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-RET-OPS-'.$suffix,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
            'payment_type' => 'credit',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 100,
            'paid_amount' => 0,
            'invoice_cash_amount' => 0,
            'remaining_amount' => 100,
        ]);

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 20,
            'discount_amount' => 0,
            'line_total' => 100,
        ]);

        app(SalesInvoiceService::class)->confirm($invoice);

        $this->assertEqualsWithDelta(5, $this->balanceQuantity($warehouse, $product), 0.0001);

        $salesReturn = SalesReturn::query()->create([
            'return_number' => 'SRT-OPS-'.$suffix,
            'customer_id' => $customer->id,
            'sales_invoice_id' => $invoice->id,
            'warehouse_id' => $warehouse->id,
            'return_date' => now()->toDateString(),
            'status' => 'draft',
            'return_reason' => 'test',
            'subtotal' => 40,
            'discount_amount' => 0,
            'total_amount' => 40,
        ]);

        SalesReturnItem::query()->create([
            'sales_return_id' => $salesReturn->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 20,
            'line_total' => 40,
        ]);

        app(SalesReturnService::class)->confirm($salesReturn);

        $this->assertEqualsWithDelta(7, $this->balanceQuantity($warehouse, $product), 0.0001);
        $returnItem = $salesReturn->items()->firstOrFail();
        $this->assertEqualsWithDelta(12, (float) $returnItem->unit_cost, 0.000001);
        $this->assertEqualsWithDelta(24, (float) $returnItem->total_cost, 0.001);

        app(SalesReturnService::class)->cancel($salesReturn->refresh());

        $this->assertEqualsWithDelta(5, $this->balanceQuantity($warehouse, $product), 0.0001);
        $this->assertEqualsWithDelta(12, $this->balanceAverageCost($warehouse, $product), 0.000001);
    }

    public function test_stale_invoice_instance_cannot_apply_inventory_twice(): void
    {
        $suffix = uniqid();

        $warehouse = $this->createWarehouse('W-LOCK-'.$suffix, 'Lock Warehouse '.$suffix);
        $customer = $this->createCustomer($suffix);
        $product = $this->createProduct($suffix);

        app(InventoryMovementService::class)->addStock(
            warehouse: $warehouse,
            product: $product,
            quantity: 10,
            movementType: 'opening_balance',
        );

        $invoice = SalesInvoice::query()->create([
            'invoice_number' => 'INV-LOCK-'.$suffix,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'invoice_date' => now()->toDateString(),
            'status' => 'draft',
            'payment_type' => 'credit',
            'subtotal' => 60,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 60,
            'paid_amount' => 0,
            'invoice_cash_amount' => 0,
            'remaining_amount' => 60,
        ]);

        SalesInvoiceItem::query()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 20,
            'discount_amount' => 0,
            'line_total' => 60,
        ]);

        $firstCopy = SalesInvoice::query()->findOrFail($invoice->id);
        $staleCopy = SalesInvoice::query()->findOrFail($invoice->id);

        app(SalesInvoiceService::class)->confirm($firstCopy);

        try {
            app(SalesInvoiceService::class)->confirm($staleCopy);
            $this->fail('A stale invoice instance must not apply inventory twice.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'لا يمكن اعتماد فاتورة ليست بحالة مسودة.',
                $exception->getMessage(),
            );
        }

        $this->assertEqualsWithDelta(7, $this->balanceQuantity($warehouse, $product), 0.0001);
        $this->assertSame(1, StockMovement::query()
            ->where('movement_type', 'sales_invoice')
            ->where('reference_type', SalesInvoice::class)
            ->where('reference_id', $invoice->id)
            ->count());
    }

    private function syncStockMovementSequenceForToday(): void
    {
        $date = now()->toDateString();
        $datePart = now()->format('Ymd');
        $prefix = 'STM-'.$datePart.'-';

        $maxExistingMovementNumber = (int) DB::table('stock_movements')
            ->where('movement_number', 'like', $prefix.'%')
            ->selectRaw('COALESCE(MAX(CAST(RIGHT(movement_number, 5) AS UNSIGNED)), 0) as max_number')
            ->value('max_number');

        $currentSequenceNumber = (int) DB::table('document_sequences')
            ->where('document_type', 'stock_movement')
            ->where('sequence_date', $date)
            ->value('last_number');

        DB::table('document_sequences')->updateOrInsert(
            [
                'document_type' => 'stock_movement',
                'sequence_date' => $date,
            ],
            [
                'last_number' => max($maxExistingMovementNumber, $currentSequenceNumber),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function createWarehouse(string $code, string $name): Warehouse
    {
        return Warehouse::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => 'main',
            'status' => 'active',
        ]);
    }

    private function createCustomer(string $suffix): Customer
    {
        return Customer::query()->create([
            'code' => 'C-OPS-'.$suffix,
            'name' => 'Ops Customer '.$suffix,
            'payment_type' => 'credit',
            'status' => 'active',
        ]);
    }

    private function createProduct(string $suffix): Product
    {
        return Product::query()->create([
            'sku' => 'P-OPS-'.$suffix,
            'name_ar' => 'Ops Product '.$suffix,
            'purchase_price' => 12,
            'sale_price' => 20,
            'status' => 'active',
        ]);
    }

    private function balanceQuantity(Warehouse $warehouse, Product $product): float
    {
        $balance = StockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('batch_key', '')
            ->where('expiry_key', '')
            ->first();

        return (float) ($balance?->quantity ?? 0);
    }

    private function balanceAverageCost(Warehouse $warehouse, Product $product): float
    {
        $balance = StockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('batch_key', '')
            ->where('expiry_key', '')
            ->first();

        return (float) ($balance?->average_unit_cost ?? 0);
    }
}
