<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_loads', function (Blueprint $table): void {
            $table->id();
            $table->string('load_number')->unique();

            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('distribution_routes')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('sales_representative_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->foreignId('from_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->cascadeOnDelete();

            $table->date('load_date');
            $table->string('status')->default('draft');

            $table->decimal('total_quantity', 14, 3)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index(['status']);
            $table->index(['load_date']);
            $table->index(['vehicle_id']);
            $table->index(['route_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_loads');
    }
};