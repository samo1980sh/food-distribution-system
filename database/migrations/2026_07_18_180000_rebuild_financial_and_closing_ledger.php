<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedSmallInteger('credit_days')
                ->default(30)
                ->after('credit_limit');
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->date('due_date')->nullable()->after('invoice_date');
            $table->decimal('credit_limit_snapshot', 14, 2)->default(0)->after('remaining_amount');
            $table->decimal('credit_exposure_before', 14, 2)->default(0)->after('credit_limit_snapshot');
            $table->decimal('credit_exposure_after', 14, 2)->default(0)->after('credit_exposure_before');
            $table->boolean('credit_limit_override_requested')->default(false)->after('credit_exposure_after');
            $table->boolean('credit_limit_overridden')->default(false)->after('credit_limit_override_requested');
            $table->text('credit_limit_override_reason')->nullable()->after('credit_limit_overridden');
            $table->foreignId('credit_limit_overridden_by')
                ->nullable()
                ->after('credit_limit_override_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('credit_limit_overridden_at')->nullable()->after('credit_limit_overridden_by');

            $table->index('due_date');
            $table->index('credit_limit_overridden');
        });

        DB::table('sales_invoices')
            ->where('payment_type', 'cash')
            ->update(['due_date' => DB::raw('invoice_date')]);

        DB::statement(<<<'SQL'
            UPDATE sales_invoices
            INNER JOIN customers ON customers.id = sales_invoices.customer_id
            SET sales_invoices.due_date = DATE_ADD(
                sales_invoices.invoice_date,
                INTERVAL COALESCE(NULLIF(customers.credit_days, 0), 30) DAY
            )
            WHERE sales_invoices.payment_type <> 'cash'
              AND sales_invoices.due_date IS NULL
        SQL);

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->date('movement_date')->nullable()->after('movement_type');
            $table->index(['movement_date', 'from_warehouse_id'], 'stock_movements_date_from_index');
            $table->index(['movement_date', 'to_warehouse_id'], 'stock_movements_date_to_index');
        });

        DB::table('stock_movements')
            ->whereNull('movement_date')
            ->update(['movement_date' => DB::raw('DATE(created_at)')]);

        Schema::table('daily_closings', function (Blueprint $table): void {
            $table->decimal('total_opening_quantity', 14, 3)->default(0)->after('status');
            $table->decimal('total_movement_in_quantity', 14, 3)->default(0)->after('total_opening_quantity');
            $table->decimal('total_movement_out_quantity', 14, 3)->default(0)->after('total_movement_in_quantity');
            $table->decimal('total_expected_quantity', 14, 3)->default(0)->after('total_movement_out_quantity');
            $table->timestamp('snapshot_at')->nullable()->after('cash_difference');
        });

        Schema::table('daily_closing_items', function (Blueprint $table): void {
            $table->decimal('opening_quantity', 14, 3)->default(0)->after('product_id');
            $table->decimal('movement_in_quantity', 14, 3)->default(0)->after('opening_quantity');
            $table->decimal('movement_out_quantity', 14, 3)->default(0)->after('movement_in_quantity');
        });

        DB::table('daily_closings')
            ->where('status', 'confirmed')
            ->whereNull('snapshot_at')
            ->update(['snapshot_at' => DB::raw('COALESCE(confirmed_at, updated_at)')]);
    }

    public function down(): void
    {
        Schema::table('daily_closing_items', function (Blueprint $table): void {
            $table->dropColumn([
                'opening_quantity',
                'movement_in_quantity',
                'movement_out_quantity',
            ]);
        });

        Schema::table('daily_closings', function (Blueprint $table): void {
            $table->dropColumn([
                'total_opening_quantity',
                'total_movement_in_quantity',
                'total_movement_out_quantity',
                'total_expected_quantity',
                'snapshot_at',
            ]);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('stock_movements_date_from_index');
            $table->dropIndex('stock_movements_date_to_index');
            $table->dropColumn('movement_date');
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropForeign(['credit_limit_overridden_by']);
            $table->dropIndex(['due_date']);
            $table->dropIndex(['credit_limit_overridden']);
            $table->dropColumn([
                'due_date',
                'credit_limit_snapshot',
                'credit_exposure_before',
                'credit_exposure_after',
                'credit_limit_override_requested',
                'credit_limit_overridden',
                'credit_limit_override_reason',
                'credit_limit_overridden_by',
                'credit_limit_overridden_at',
            ]);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('credit_days');
        });
    }
};
