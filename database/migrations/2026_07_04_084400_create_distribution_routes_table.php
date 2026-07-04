<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('sales_representative_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->json('visit_days')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['area_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_routes');
    }
};