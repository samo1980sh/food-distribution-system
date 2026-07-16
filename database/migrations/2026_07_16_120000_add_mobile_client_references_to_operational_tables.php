<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $tables = [
        'sales_invoices' => 'sales_invoices_creator_client_ref_unique',
        'customer_payments' => 'customer_payments_creator_client_ref_unique',
        'sales_returns' => 'sales_returns_creator_client_ref_unique',
        'vehicle_expenses' => 'vehicle_expenses_creator_client_ref_unique',
        'daily_closings' => 'daily_closings_creator_client_ref_unique',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $indexName) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->string('client_reference', 100)->nullable()->after('created_by');
                $blueprint->char('client_payload_hash', 64)->nullable()->after('client_reference');
                $blueprint->unique(['created_by', 'client_reference'], $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $indexName) {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->dropUnique($indexName);
                $blueprint->dropColumn(['client_reference', 'client_payload_hash']);
            });
        }
    }
};
