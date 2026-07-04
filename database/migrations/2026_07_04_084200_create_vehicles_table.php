<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('plate_number')->unique();
            $table->string('name')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->decimal('capacity', 12, 3)->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('current_odometer')->nullable();
            $table->date('insurance_expiry_date')->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['plate_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};