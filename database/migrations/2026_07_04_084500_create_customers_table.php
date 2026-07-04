<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('owner_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('customer_type')->default('grocery');
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('distribution_routes')->nullOnDelete();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->string('payment_type')->default('cash');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_type']);
            $table->index(['status']);
            $table->index(['area_id']);
            $table->index(['route_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};