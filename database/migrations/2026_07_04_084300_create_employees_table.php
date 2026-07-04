<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->string('type')->default('driver');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};