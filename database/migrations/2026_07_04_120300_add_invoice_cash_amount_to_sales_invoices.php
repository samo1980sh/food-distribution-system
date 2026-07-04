<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->decimal('invoice_cash_amount', 14, 2)->default(0)->after('paid_amount');
        });

        DB::table('sales_invoices')
            ->where('status', 'confirmed')
            ->update(['invoice_cash_amount' => DB::raw('paid_amount')]);
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropColumn('invoice_cash_amount');
        });
    }
};
