<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_loads', function (Blueprint $table): void {
            $table->string('handover_status')->default('pending')->after('status');
            $table->text('handover_notes')->nullable()->after('notes');
            $table->foreignId('handover_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('handover_at')->nullable()->after('handover_by');

            $table->index(['handover_status']);
        });

        Schema::table('vehicle_load_items', function (Blueprint $table): void {
            $table->decimal('received_quantity', 14, 3)->nullable()->after('quantity');
            $table->string('handover_note', 1000)->nullable()->after('received_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_load_items', function (Blueprint $table): void {
            $table->dropColumn(['received_quantity', 'handover_note']);
        });

        Schema::table('vehicle_loads', function (Blueprint $table): void {
            $table->dropForeign(['handover_by']);
            $table->dropIndex(['handover_status']);
            $table->dropColumn(['handover_status', 'handover_notes', 'handover_by', 'handover_at']);
        });
    }
};
