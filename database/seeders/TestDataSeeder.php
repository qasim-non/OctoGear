<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Backwards-compatible entry point for DB_SEED_TEST_DATA. */
class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoDataSeeder::class);
    }
}
