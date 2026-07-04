<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();

            $table->index(['warehouse_id', 'product_id']);
            $table->index(['batch_number']);
            $table->index(['expiry_date']);
            $table->unique(
                ['warehouse_id', 'product_id', 'batch_number', 'expiry_date'],
                'stock_balances_unique_batch'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};