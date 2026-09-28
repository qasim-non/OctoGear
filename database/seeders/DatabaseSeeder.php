<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (config('database.seed_test_data', false) && ! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeding is allowed only in local/testing environments.');
        }

        $this->call(ProductionDataSeeder::class);

        if (config('database.seed_test_data', false)) {
            $this->call([
                TestDataSeeder::class,
            ]);
        }

        $this->command?->info('Database seeding completed!');
    }
}
