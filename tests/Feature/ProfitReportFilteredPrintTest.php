<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfitReportFilteredPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prints_only_entries_matching_the_encoded_filters(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ACCOUNTANT,
            'status' => User::STATUS_ACTIVE,
        ]);

        Schema::disableForeignKeyConstraints();

        try {
            $this->insertReportData();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $state = $this->encodeState([
            'filters' => [
                'entry_date' => [
                    'from' => '2026-07-10',
                    'until' => '2026-07-10',
                ],
                'entry_type' => [
                    'value' => 'invoice',
                ],
            ],
            'search' => 'INV-PRINT',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('reports.profit.print-filtered', [
                'state' => $state,
            ]));

        $response
            ->assertOk()
            ->assertSee('تقرير الأرباح التقريبية')
            ->assertSee('INV-PRINT-001')
            ->assertDontSee('SRT-PRINT-001')
            ->assertSee('1,000.00')
            ->assertSee('400.00');
    }

    private function insertReportData(): void
    {
        DB::table('sales_invoices')->insert([
            'id' => 1,
            'invoice_number' => 'INV-PRINT-001',
            'customer_id' => 1,
            'vehicle_id' => null,
            'route_id' => null,
            'warehouse_id' => 1,
            'sales_representative_id' => null,
            'invoice_date' => '2026-07-10',
            'status' => 'confirmed',
            'payment_type' => 'cash',
            'subtotal' => 1000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'paid_amount' => 1000,
            'invoice_cash_amount' => 1000,
            'remaining_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales_invoice_items')->insert([
            'sales_invoice_id' => 1,
            'product_id' => 1,
            'batch_number' => null,
            'expiry_date' => null,
            'quantity' => 2,
            'unit_price' => 500,
            'unit_cost' => 300,
            'discount_amount' => 0,
            'line_total' => 1000,
            'total_cost' => 600,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales_returns')->insert([
            'id' => 1,
            'return_number' => 'SRT-PRINT-001',
            'customer_id' => 1,
            'sales_invoice_id' => 1,
            'vehicle_id' => null,
            'route_id' => null,
            'warehouse_id' => 1,
            'sales_representative_id' => null,
            'return_date' => '2026-07-11',
            'status' => 'confirmed',
            'return_reason' => null,
            'subtotal' => 250,
            'discount_amount' => 0,
            'total_amount' => 250,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales_return_items')->insert([
            'sales_return_id' => 1,
            'product_id' => 1,
            'batch_number' => null,
            'expiry_date' => null,
            'quantity' => 1,
            'unit_price' => 250,
            'unit_cost' => 150,
            'line_total' => 250,
            'total_cost' => 150,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
