<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('created_by')
                ->nullable()
                ->after('notes')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('client_reference', 100)->nullable()->after('created_by');
            $table->char('client_payload_hash', 64)->nullable()->after('client_reference');
            $table->string('operation_source', 40)->default('legacy')->after('client_payload_hash');
            $table->unique(
                ['created_by', 'client_reference'],
                'customers_created_by_client_reference_unique',
            );
            $table->index('operation_source');
        });

        Schema::create('sales_journeys', function (Blueprint $table): void {
            $table->id();
            $table->string('journey_number')->unique();
            $table->date('journey_date');
            $table->foreignId('route_id')->constrained('distribution_routes')->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('sales_representative_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status')->default('ready');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('start_notes')->nullable();
            $table->text('finish_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operation_source', 40)->default('mobile_sales');
            $table->timestamps();

            $table->unique(
                ['journey_date', 'route_id', 'sales_representative_id'],
                'sales_journeys_date_route_representative_unique',
            );
            $table->index(['journey_date', 'status']);
            $table->index(['sales_representative_id', 'journey_date']);
            $table->index('operation_source');
        });

        Schema::create('sales_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_journey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('route_id')->constrained('distribution_routes')->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('sales_representative_id')->constrained('employees')->restrictOnDelete();
            $table->unsignedInteger('planned_sequence')->default(1);
            $table->string('status')->default('pending');
            $table->string('outcome')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->decimal('completion_latitude', 10, 7)->nullable();
            $table->decimal('completion_longitude', 10, 7)->nullable();
            $table->text('start_notes')->nullable();
            $table->text('completion_notes')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['sales_journey_id', 'customer_id'],
                'sales_visits_journey_customer_unique',
            );
            $table->unique(
                ['sales_journey_id', 'planned_sequence'],
                'sales_visits_journey_sequence_unique',
            );
            $table->index(['route_id', 'sales_representative_id']);
            $table->index(['sales_journey_id', 'status']);
        });

        foreach (['sales_invoices', 'customer_payments', 'sales_returns'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('sales_visit_id')
                    ->nullable()
                    ->after('sales_representative_id')
                    ->constrained('sales_visits')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['sales_returns', 'customer_payments', 'sales_invoices'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('sales_visit_id');
            });
        }

        Schema::dropIfExists('sales_visits');
        Schema::dropIfExists('sales_journeys');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_created_by_client_reference_unique');
            $table->dropIndex(['operation_source']);
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'created_by',
                'client_reference',
                'client_payload_hash',
                'operation_source',
            ]);
        });
    }
};
