<?php

namespace Database\Seeders;

use Database\Seeders\Demo\ProfessionalCatalogSeeder;
use Database\Seeders\Demo\ProfessionalOperationsSeeder;
use Database\Seeders\Demo\ProfessionalUsersAndDistributionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProfessionalDemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call([
                RolesAndPermissionsSeeder::class,
                ProfessionalCatalogSeeder::class,
                ProfessionalUsersAndDistributionSeeder::class,
                ProfessionalOperationsSeeder::class,
            ]);
        });
    }
}
