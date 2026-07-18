<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $vehicleWarehouseWithoutVehicle = DB::table('warehouses')
            ->where('type', 'vehicle')
            ->whereNull('vehicle_id')
            ->exists();

        if ($vehicleWarehouseWithoutVehicle) {
            throw new RuntimeException(
                'تعذر إضافة قيد مستودع السيارة: يوجد مستودع من نوع سيارة غير مرتبط بسيارة.',
            );
        }

        $nonVehicleWarehouseWithVehicle = DB::table('warehouses')
            ->where('type', '!=', 'vehicle')
            ->whereNotNull('vehicle_id')
            ->exists();

        if ($nonVehicleWarehouseWithVehicle) {
            throw new RuntimeException(
                'تعذر إضافة قيد مستودع السيارة: يوجد مستودع رئيسي أو فرعي مرتبط بسيارة.',
            );
        }

        $duplicateVehicleIds = DB::table('warehouses')
            ->whereNotNull('vehicle_id')
            ->groupBy('vehicle_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('vehicle_id');

        if ($duplicateVehicleIds->isNotEmpty()) {
            throw new RuntimeException(
                'تعذر إضافة قيد مستودع السيارة: توجد سيارات مرتبطة بأكثر من مستودع: '
                .$duplicateVehicleIds->implode(', '),
            );
        }

        Schema::table('warehouses', function (Blueprint $table): void {
            $table->unique('vehicle_id', 'warehouses_vehicle_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropUnique('warehouses_vehicle_id_unique');
        });
    }
};
