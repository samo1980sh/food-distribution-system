<?php

namespace Tests\Feature;

use App\Http\Resources\Api\V1\Operational\ProductResource;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProductOperationalUnitClarityTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_resource_exposes_the_canonical_quantity_unit_label(): void
    {
        $unit = Unit::query()->create([
            'code' => 'CRT',
            'name_ar' => 'كرتونة',
            'symbol' => 'كرتونة',
            'status' => 'active',
        ]);

        $product = Product::query()->create([
            'sku' => 'UNIT-CLARITY-001',
            'name_ar' => 'منتج اختبار الوحدة',
            'unit_id' => $unit->id,
            'purchase_price' => 100,
            'sale_price' => 120,
            'wholesale_price' => 110,
            'min_stock' => 2,
            'has_expiry' => false,
            'status' => 'active',
        ])->load('unit');

        $payload = ProductResource::make($product)
            ->resolve(Request::create('/api/v1/products'));

        $this->assertSame('كرتونة', $payload['quantity_unit_label']);
        $this->assertSame('كرتونة', $payload['unit']['name']);
        $this->assertSame('120.00', $payload['sale_price']);
    }

    public function test_missing_product_unit_is_explicit_in_api_contract(): void
    {
        $product = Product::query()->create([
            'sku' => 'UNIT-CLARITY-002',
            'name_ar' => 'منتج بلا وحدة',
            'purchase_price' => 0,
            'sale_price' => 0,
            'wholesale_price' => 0,
            'min_stock' => 0,
            'has_expiry' => false,
            'status' => 'active',
        ])->load('unit');

        $payload = ProductResource::make($product)
            ->resolve(Request::create('/api/v1/products'));

        $this->assertNull($payload['unit']);
        $this->assertSame('وحدة غير محددة', $payload['quantity_unit_label']);
    }

    public function test_laravel_operational_forms_explain_one_unit_contract_without_conversion_math(): void
    {
        $productForm = file_get_contents(
            app_path('Filament/Resources/Products/Schemas/ProductForm.php'),
        );
        $loadForm = file_get_contents(
            app_path('Filament/Resources/VehicleLoads/Schemas/VehicleLoadForm.php'),
        );
        $purchaseForm = file_get_contents(
            app_path('Filament/Resources/PurchaseOrders/Schemas/PurchaseOrderForm.php'),
        );
        $purchaseTable = file_get_contents(
            app_path('Filament/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php'),
        );

        $this->assertIsString($productForm);
        $this->assertIsString($loadForm);
        $this->assertIsString($purchaseForm);
        $this->assertIsString($purchaseTable);

        $this->assertStringContainsString("->label('وحدة التشغيل')", $productForm);
        $this->assertStringContainsString('المخزون والتحميل والبيع والمرتجعات والمشتريات', $productForm);
        $this->assertStringContainsString("->label('الكمية بوحدة التشغيل')", $loadForm);
        $this->assertStringContainsString("->label('الكمية بوحدة التشغيل')", $purchaseForm);
        $this->assertStringContainsString("->label('تكلفة وحدة التشغيل')", $purchaseForm);
        $this->assertStringContainsString("TextInput::make('unit_label')", $purchaseTable);
        $this->assertStringNotContainsString('conversion_factor', $productForm);
        $this->assertStringNotContainsString('sales_unit_id', $productForm);
        $this->assertStringNotContainsString('purchase_unit_id', $productForm);
    }
}
