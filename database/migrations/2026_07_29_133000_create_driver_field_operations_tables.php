<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_journeys', function (Blueprint $table): void {
            $table->id();
            $table->string('journey_number')->unique();
            $table->date('journey_date');
            $table->foreignId('route_id')->constrained('distribution_routes')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('sales_representative_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status')->default('ready');
            $table->decimal('start_odometer', 14, 2)->nullable();
            $table->decimal('end_odometer', 14, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('start_notes')->nullable();
            $table->text('finish_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operation_source')->default('mobile_driver');
            $table->timestamps();

            $table->unique(['journey_date', 'route_id', 'driver_id'], 'driver_journeys_day_route_driver_unique');
            $table->index(['status', 'journey_date']);
            $table->index(['vehicle_id', 'journey_date']);
            $table->index(['warehouse_id', 'journey_date']);
        });

        Schema::create('driver_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_journey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('route_id')->constrained('distribution_routes')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('sales_representative_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->decimal('expected_quantity', 14, 3)->default(0);
            $table->decimal('delivered_quantity', 14, 3)->default(0);
            $table->decimal('returned_quantity', 14, 3)->default(0);
            $table->boolean('return_required')->default(false);
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('proof_note')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('outcome_submitted_at')->nullable();
            $table->foreignId('outcome_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('sales_invoice_id');
            $table->index(['driver_journey_id', 'status']);
            $table->index(['route_id', 'driver_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('driver_delivery_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('expected_quantity', 14, 3);
            $table->decimal('delivered_quantity', 14, 3)->default(0);
            $table->decimal('returned_quantity', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['driver_delivery_id', 'sales_invoice_item_id'], 'driver_delivery_items_invoice_item_unique');
            $table->index(['product_id', 'batch_number', 'expiry_date'], 'driver_delivery_items_product_batch_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_delivery_items');
        Schema::dropIfExists('driver_deliveries');
        Schema::dropIfExists('driver_journeys');
    }
};
