<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_closing_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_closing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->decimal('loaded_quantity', 14, 3)->default(0);
            $table->decimal('sold_quantity', 14, 3)->default(0);
            $table->decimal('returned_quantity', 14, 3)->default(0);

            $table->decimal('expected_quantity', 14, 3)->default(0);
            $table->decimal('actual_quantity', 14, 3)->nullable();
            $table->decimal('difference_quantity', 14, 3)->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['daily_closing_id', 'product_id'], 'daily_closing_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closing_items');
    }
};