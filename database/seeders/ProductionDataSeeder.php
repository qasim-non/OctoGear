<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductionDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(fn () => $this->call([
            ReferenceDataSeeder::class,
            CarDataSeeder::class,
            ComponentSeeder::class,
            PlatformDataSeeder::class,
        ]));

        $this->command?->info('Production reference data is ready; no demo accounts or transactions were created.');
    }
}
