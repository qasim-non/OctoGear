<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            CarDataSeeder::class,
            ComponentSeeder::class,
            PlatformDataSeeder::class,
        ]);

        if (config('database.seed_test_data', false)) {
            $this->call([
                TestDataSeeder::class,
            ]);
        }

        $this->command->info('Database seeding completed!');
    }
}
