<?php

use App\Enums\OperationSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'sales_invoices',
        'customer_payments',
        'sales_returns',
        'vehicle_expenses',
        'daily_closings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('operation_source', 40)
                    ->default(OperationSource::LEGACY->value)
                    ->after('client_payload_hash');
                $blueprint->text('administrative_reason')
                    ->nullable()
                    ->after('operation_source');
                $blueprint->index('operation_source');
            });
        }

        foreach ([
            'sales_invoices',
            'customer_payments',
            'sales_returns',
            'daily_closings',
        ] as $table) {
            DB::table($table)
                ->whereNotNull('client_reference')
                ->update(['operation_source' => OperationSource::MOBILE_SALES->value]);
        }

        DB::table('vehicle_expenses')
            ->whereNotNull('client_reference')
            ->update(['operation_source' => OperationSource::MOBILE_DRIVER->value]);
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropIndex(['operation_source']);
                $blueprint->dropColumn([
                    'operation_source',
                    'administrative_reason',
                ]);
            });
        }
    }
};
