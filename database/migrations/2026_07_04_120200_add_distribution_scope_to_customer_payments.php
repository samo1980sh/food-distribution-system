<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table): void {
            $table->foreignId('vehicle_id')->nullable()->after('sales_invoice_id')->constrained()->nullOnDelete();
            $table->foreignId('route_id')->nullable()->after('vehicle_id')->constrained('distribution_routes')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('route_id')->constrained('warehouses')->nullOnDelete();

            $table->index(['vehicle_id']);
            $table->index(['warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropConstrainedForeignId('route_id');
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
