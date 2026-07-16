<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_sync_push_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 100);
            $table->string('batch_id', 100);
            $table->char('request_hash', 64);
            $table->string('status', 20)->default('processing');
            $table->unsignedSmallInteger('operation_count')->default(0);
            $table->unsignedSmallInteger('applied_count')->default(0);
            $table->unsignedSmallInteger('replayed_count')->default(0);
            $table->unsignedSmallInteger('conflict_count')->default(0);
            $table->unsignedSmallInteger('failed_count')->default(0);
            $table->json('response_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id', 'batch_id'], 'mobile_sync_push_batches_unique');
            $table->index(['status', 'processed_at'], 'mobile_sync_push_batches_status_index');
        });

        Schema::create('mobile_sync_push_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 100);
            $table->string('batch_id', 100);
            $table->string('operation_id', 100);
            $table->char('request_hash', 64);
            $table->string('entity', 50);
            $table->string('action', 30);
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('client_reference', 100)->nullable();
            $table->string('base_version', 40)->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id', 'operation_id'], 'mobile_sync_push_operations_unique');
            $table->index(['user_id', 'device_id', 'batch_id'], 'mobile_sync_push_operations_batch_index');
            $table->index(['entity', 'record_id'], 'mobile_sync_push_operations_record_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_sync_push_operations');
        Schema::dropIfExists('mobile_sync_push_batches');
    }
};
