<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('expense_number')->unique();

            $table->date('expense_date');

            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('distribution_routes')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('sales_representative_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->string('expense_type');
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('payment_method')->default('cash');
            $table->string('receipt_path')->nullable();

            $table->string('status')->default('pending');

            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();

            $table->index(['expense_date']);
            $table->index(['vehicle_id']);
            $table->index(['warehouse_id']);
            $table->index(['route_id']);
            $table->index(['driver_id']);
            $table->index(['sales_representative_id']);
            $table->index(['expense_type']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_expenses');
    }
};