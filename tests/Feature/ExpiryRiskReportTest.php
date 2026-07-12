<?php

namespace Tests\Feature;

use App\Filament\Resources\ExpiryRiskReports\ExpiryRiskReportResource;
use App\Filament\Resources\ExpiryRiskReports\Tables\ExpiryRiskReportsTable;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExpiryRiskReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_report_query_contains_positive_expiry_products_only(): void
    {
        $this->insertInventoryData();

        $ids = ExpiryRiskReportResource::getEloquentQuery()
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([301, 302, 303, 304], $ids);
    }

    public function test_default_risk_scope_contains_missing_expired_and_within_thirty_days(): void
    {
        $this->insertInventoryData();

        $query = ExpiryRiskReportResource::getEloquentQuery();

        ExpiryRiskReportsTable::applyExpiryFilter($query, [
            'scope' => 'risk_30',
        ]);

        $ids = $query->orderBy('id')->pluck('id')->all();

        $this->assertSame([301, 302, 303], $ids);
    }

    public function test_row_print_action_resolves_the_balance_print_route(): void
    {
        $balance = new StockBalance();
        $balance->forceFill(['id' => 77]);

        $this->assertSame(
            route('reports.expiry-risk.print', [
                'stockBalance' => 77,
            ]),
            ExpiryRiskReportsTable::printUrlFor($balance),
        );
    }

    public function test_filtered_print_applies_expiry_status_and_excludes_other_balances(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->insertInventoryData();

        $state = $this->encodeState([
            'filters' => [
                'expiry_risk' => [
                    'scope' => 'all',
                    'status' => 'expired',
                    'from' => null,
                    'until' => null,
                ],
            ],
            'search' => '',
        ]);

        $this
            ->actingAs($user)
            ->get(route('reports.expiry-risk.print-filtered', [
                'state' => $state,
            ]))
            ->assertOk()
            ->assertSee('تقرير المواد القريبة من الانتهاء')
            ->assertSee('رصيد منتهي')
            ->assertDontSee('رصيد بدون تاريخ')
            ->assertDontSee('رصيد قريب')
            ->assertDontSee('رصيد بعيد');
    }

    public function test_single_print_shows_missing_expiry_warning(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->insertInventoryData();

        $this
            ->actingAs($user)
            ->get(route('reports.expiry-risk.print', [
                'stockBalance' => 301,
            ]))
            ->assertOk()
            ->assertSee('بطاقة صلاحية رصيد')
            ->assertSee('تاريخ الصلاحية غير مسجل')
            ->assertSee('رصيد بدون تاريخ');
    }

    private function insertInventoryData(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            DB::table('warehouses')->insert([
                [
                    'id' => 101,
                    'vehicle_id' => null,
                    'code' => 'WH-TEST',
                    'name' => 'مستودع الاختبار',
                    'type' => 'main',
                    'address' => null,
                    'status' => 'active',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('products')->insert([
                [
                    'id' => 201,
                    'sku' => 'EXP-001',
                    'barcode' => null,
                    'name_ar' => 'رصيد بدون تاريخ',
                    'category_id' => null,
                    'unit_id' => null,
                    'purchase_price' => 100,
                    'sale_price' => 150,
                    'wholesale_price' => 130,
                    'min_stock' => 0,
                    'has_expiry' => true,
                    'status' => 'active',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 202,
                    'sku' => 'EXP-002',
                    'barcode' => null,
                    'name_ar' => 'رصيد منتهي',
                    'category_id' => null,
                    'unit_id' => null,
                    'purchase_price' => 100,
                    'sale_price' => 150,
                    'wholesale_price' => 130,
                    'min_stock' => 0,
                    'has_expiry' => true,
                    'status' => 'active',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 203,
                    'sku' => 'EXP-003',
                    'barcode' => null,
                    'name_ar' => 'رصيد قريب',
                    'category_id' => null,
                    'unit_id' => null,
                    'purchase_price' => 100,
                    'sale_price' => 150,
                    'wholesale_price' => 130,
                    'min_stock' => 0,
                    'has_expiry' => true,
                    'status' => 'active',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 204,
                    'sku' => 'EXP-004',
                    'barcode' => null,
                    'name_ar' => 'رصيد بعيد',
                    'category_id' => null,
                    'unit_id' => null,
                    'purchase_price' => 100,
                    'sale_price' => 150,
                    'wholesale_price' => 130,
                    'min_stock' => 0,
                    'has_expiry' => true,
                    'status' => 'active',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 205,
                    'sku' => 'NO-EXP-001',
                    'barcode' => null,
                    'name_ar' => 'منتج لا يتطلب صلاحية',
                    'category_id' => null,
                    'unit_id' => null,
                    'purchase_price' => 100,
                    'sale_price' => 150,
                    'wholesale_price' => 130,
                    'min_stock' => 0,
                    'has_expiry' => false,
                    'status' => 'active',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('stock_balances')->insert([
                $this->balanceRow(301, 201, null, 10),
                $this->balanceRow(302, 202, today()->subDay()->toDateString(), 20),
                $this->balanceRow(303, 203, today()->addDays(10)->toDateString(), 30),
                $this->balanceRow(304, 204, today()->addDays(90)->toDateString(), 40),
                $this->balanceRow(305, 205, null, 50),
                $this->balanceRow(306, 201, null, 0),
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function balanceRow(
        int $id,
        int $productId,
        ?string $expiryDate,
        float $quantity,
    ): array {
        return [
            'id' => $id,
            'warehouse_id' => 101,
            'product_id' => $productId,
            'batch_number' => 'B-'.$id,
            'batch_key' => 'B-'.$id,
            'expiry_date' => $expiryDate,
            'expiry_key' => $expiryDate ?? '',
            'quantity' => $quantity,
            'average_unit_cost' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function encodeState(array $state): string
    {
        $json = json_encode(
            $state,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        $this->assertNotFalse($json);

        return rtrim(
            strtr(base64_encode($json), '+/', '-_'),
            '=',
        );
    }
}
