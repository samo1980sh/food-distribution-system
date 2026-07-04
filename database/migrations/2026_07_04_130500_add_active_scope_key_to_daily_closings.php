<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('daily_closings')
            ->selectRaw('DATE(closing_date) as closing_day, warehouse_id, COUNT(*) as aggregate')
            ->where('status', '!=', 'cancelled')
            ->groupByRaw('DATE(closing_date), warehouse_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new RuntimeException('Ù„Ø§ ÙŠÙ…ÙƒÙ† Ø¥Ø¶Ø§ÙØ© Ù‚ÙŠØ¯ Ù…Ù†Ø¹ ØªÙƒØ±Ø§Ø± Ø§Ù„Ø¥ØºÙ„Ø§Ù‚ Ù‚Ø¨Ù„ Ù…Ø¹Ø§Ù„Ø¬Ø© Ø§Ù„Ø¥ØºÙ„Ø§Ù‚Ø§Øª Ø§Ù„ÙØ¹Ù‘Ø§Ù„Ø© Ø§Ù„Ù…ÙƒØ±Ø±Ø© Ù„Ù†ÙØ³ Ø§Ù„ØªØ§Ø±ÙŠØ® ÙˆØ§Ù„Ù…Ø³ØªÙˆØ¯Ø¹.');
        }

        Schema::table('daily_closings', function (Blueprint $table): void {
            $table->string('active_scope_key', 64)->nullable()->after('status');
        });

        DB::table('daily_closings')
            ->where('status', '!=', 'cancelled')
            ->orderBy('id')
            ->chunkById(100, function ($closings): void {
                foreach ($closings as $closing) {
                    DB::table('daily_closings')
                        ->where('id', $closing->id)
                        ->update([
                            'active_scope_key' => date('Y-m-d', strtotime($closing->closing_date)).'|'.$closing->warehouse_id,
                        ]);
                }
            });

        Schema::table('daily_closings', function (Blueprint $table): void {
            $table->unique('active_scope_key', 'daily_closings_active_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table): void {
            $table->dropUnique('daily_closings_active_scope_unique');
            $table->dropColumn('active_scope_key');
        });
    }
};

