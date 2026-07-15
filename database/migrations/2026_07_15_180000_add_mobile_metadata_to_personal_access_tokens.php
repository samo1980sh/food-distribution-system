<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->string('device_id', 100)->nullable()->after('name');
            $table->string('device_name', 100)->nullable()->after('device_id');
            $table->string('platform', 20)->nullable()->after('device_name');
            $table->string('app_version', 30)->nullable()->after('platform');
            $table->string('ip_address', 45)->nullable()->after('app_version');
            $table->timestamp('last_seen_at')->nullable()->after('last_used_at');

            $table->unique(
                ['tokenable_type', 'tokenable_id', 'device_id'],
                'personal_access_tokens_device_unique',
            );
            $table->index(['tokenable_id', 'last_seen_at'], 'personal_access_tokens_seen_index');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropUnique('personal_access_tokens_device_unique');
            $table->dropIndex('personal_access_tokens_seen_index');
            $table->dropColumn([
                'device_id',
                'device_name',
                'platform',
                'app_version',
                'ip_address',
                'last_seen_at',
            ]);
        });
    }
};
