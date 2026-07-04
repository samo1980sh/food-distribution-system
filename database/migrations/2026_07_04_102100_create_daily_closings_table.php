<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_closings', function (Blueprint $table): void {
            $table->id();
            $table->string('closing_number')->unique();

            $table->date('closing_date');

            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('distribution_routes')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('sales_representative_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->string('status')->default('draft');

            $table->decimal('total_loaded_quantity', 14, 3)->default(0);
            $table->decimal('total_sold_quantity', 14, 3)->default(0);
            $table->decimal('total_returned_quantity', 14, 3)->default(0);

            $table->decimal('total_sales_amount', 14, 2)->default(0);
            $table->decimal('total_returns_amount', 14, 2)->default(0);
            $table->decimal('total_collections_amount', 14, 2)->default(0);

            $table->decimal('expected_cash_amount', 14, 2)->default(0);
            $table->decimal('actual_cash_amount', 14, 2)->default(0);
            $table->decimal('cash_difference', 14, 2)->default(0);

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->index(['closing_date']);
            $table->index(['vehicle_id']);
            $table->index(['warehouse_id']);
            $table->index(['sales_representative_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
    }
};