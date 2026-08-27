<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_journeys', function (Blueprint $table): void {
            $table->unsignedInteger('start_odometer')->nullable()->after('finished_at');
            $table->unsignedInteger('end_odometer')->nullable()->after('start_odometer');
            $table->unsignedInteger('distance_km')->nullable()->after('end_odometer');
        });

        Schema::table('vehicle_expenses', function (Blueprint $table): void {
            $table->unsignedInteger('odometer_reading')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_expenses', function (Blueprint $table): void {
            $table->dropColumn('odometer_reading');
        });

        Schema::table('sales_journeys', function (Blueprint $table): void {
            $table->dropColumn(['start_odometer', 'end_odometer', 'distance_km']);
        });
    }
};
