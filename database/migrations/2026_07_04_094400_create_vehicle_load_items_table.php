<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_load_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_load_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();

            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);

            $table->timestamps();

            $table->index(['vehicle_load_id']);
            $table->index(['product_id']);
            $table->index(['batch_number']);
            $table->index(['expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_load_items');
    }
};