<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_closings', function (Blueprint $table): void {
            if (! Schema::hasColumn('daily_closings', 'total_vehicle_expenses_amount')) {
                $table->decimal('total_vehicle_expenses_amount', 14, 2)->default(0)->after('non_cash_collections_amount');
            }

            if (! Schema::hasColumn('daily_closings', 'cash_vehicle_expenses_amount')) {
                $table->decimal('cash_vehicle_expenses_amount', 14, 2)->default(0)->after('total_vehicle_expenses_amount');
            }

            if (! Schema::hasColumn('daily_closings', 'non_cash_vehicle_expenses_amount')) {
                $table->decimal('non_cash_vehicle_expenses_amount', 14, 2)->default(0)->after('cash_vehicle_expenses_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table): void {
            foreach ([
                'non_cash_vehicle_expenses_amount',
                'cash_vehicle_expenses_amount',
                'total_vehicle_expenses_amount',
            ] as $column) {
                if (Schema::hasColumn('daily_closings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};