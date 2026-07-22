<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_closings', function (Blueprint $table): void {
            $table->boolean('field_workflow')->default(false)->after('sales_representative_id');
            $table->foreignId('driver_id')
                ->nullable()
                ->after('field_workflow')
                ->constrained('employees')
                ->nullOnDelete();

            $table->foreignId('inventory_submitted_by')
                ->nullable()
                ->after('notes')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('inventory_submitted_at')->nullable()->after('inventory_submitted_by');

            $table->text('cash_notes')->nullable()->after('inventory_submitted_at');
            $table->foreignId('cash_submitted_by')
                ->nullable()
                ->after('cash_notes')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('cash_submitted_at')->nullable()->after('cash_submitted_by');

            $table->index(['field_workflow', 'status'], 'daily_closings_field_status_index');
            $table->index('inventory_submitted_at');
            $table->index('cash_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table): void {
            $table->dropIndex('daily_closings_field_status_index');
            $table->dropIndex(['inventory_submitted_at']);
            $table->dropIndex(['cash_submitted_at']);

            $table->dropConstrainedForeignId('cash_submitted_by');
            $table->dropColumn(['cash_submitted_at', 'cash_notes']);

            $table->dropConstrainedForeignId('inventory_submitted_by');
            $table->dropColumn('inventory_submitted_at');

            $table->dropConstrainedForeignId('driver_id');
            $table->dropColumn('field_workflow');
        });
    }
};
