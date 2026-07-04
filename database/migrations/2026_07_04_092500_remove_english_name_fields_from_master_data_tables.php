<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table): void {
            $table->dropColumn('name_en');
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropColumn('name_en');
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->dropColumn('name_en');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('name_en');
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table): void {
            $table->string('name_en')->nullable();
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->string('name_en')->nullable();
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->string('name_en')->nullable();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('name_en')->nullable();
        });
    }
};