<?php

namespace Database\Seeders;

use Database\Seeders\Demo\CleanOpeningInventorySeeder;
use Database\Seeders\Demo\ProfessionalCatalogSeeder;
use Database\Seeders\Demo\ProfessionalUsersAndDistributionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanTestBaselineSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([
                RolesAndPermissionsSeeder::class,
                ProfessionalCatalogSeeder::class,
                ProfessionalUsersAndDistributionSeeder::class,
                CleanOpeningInventorySeeder::class,
            ]);

            $this->clearMobileRuntimeState();
        });
    }

    private function clearMobileRuntimeState(): void
    {
        foreach ([
            'mobile_sync_push_operations',
            'mobile_sync_push_batches',
            'mobile_sync_checkpoints',
            'mobile_sync_states',
            'mobile_sync_changes',
            'personal_access_tokens',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }
}
