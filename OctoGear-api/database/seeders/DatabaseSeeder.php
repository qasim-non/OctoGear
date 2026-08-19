<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,    // 1. Countries, Cities, FuelTypes, Colors
            CarDataSeeder::class,          // 2. Car companies, names, models
            ComponentSeeder::class,        // 3. Car sections, components
            PlatformDataSeeder::class,     // 4. Settings, CMS, default admin
        ]);

        $this->command->info('Database seeding completed!');
    }
}
