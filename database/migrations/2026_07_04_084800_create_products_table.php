<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('sale_price', 14, 2)->default(0);
            $table->decimal('wholesale_price', 14, 2)->default(0);
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->boolean('has_expiry')->default(true);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['category_id']);
            $table->index(['unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};