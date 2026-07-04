<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_closings', function (Blueprint $table): void {
            $table->decimal('invoice_cash_amount', 14, 2)->default(0)->after('total_collections_amount');
            $table->decimal('cash_collections_amount', 14, 2)->default(0)->after('invoice_cash_amount');
            $table->decimal('bank_transfer_collections_amount', 14, 2)->default(0)->after('cash_collections_amount');
            $table->decimal('cheque_collections_amount', 14, 2)->default(0)->after('bank_transfer_collections_amount');
            $table->decimal('other_collections_amount', 14, 2)->default(0)->after('cheque_collections_amount');
            $table->decimal('non_cash_collections_amount', 14, 2)->default(0)->after('other_collections_amount');
        });
    }

    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table): void {
            $table->dropColumn([
                'invoice_cash_amount',
                'cash_collections_amount',
                'bank_transfer_collections_amount',
                'cheque_collections_amount',
                'other_collections_amount',
                'non_cash_collections_amount',
            ]);
        });
    }
};
