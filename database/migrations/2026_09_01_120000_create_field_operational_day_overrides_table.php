<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_operational_day_overrides', function (Blueprint $table): void {
            $table->id();
            $table->date('operation_date');
            $table->foreignId('route_id')->constrained('distribution_routes')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('sales_representative_id')->constrained('employees')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['operation_date', 'route_id'], 'field_operation_day_route_unique');
            $table->index(['operation_date', 'status']);
            $table->index(['sales_representative_id', 'operation_date'], 'field_operation_rep_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_operational_day_overrides');
    }
};
