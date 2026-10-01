<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GeneralOrderMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_schema_contains_only_the_current_request_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('orders', ['component_id', 'component_name']));
        foreach (['model_id', 'component_name_ar', 'component_name_en'] as $column) {
            $this->assertFalse(Schema::hasColumn('orders', $column));
        }
        $this->assertTrue(Schema::hasColumns('order_vehicle_details', [
            'color_id', 'fuel_type', 'color_name_en', 'color_name_ar', 'fuel_type_en', 'fuel_type_ar',
        ]));
        $this->assertFalse(Schema::hasColumn('order_vehicle_details', 'legacy_model'));
        $this->assertDatabaseCount('order_vehicle_details', 0);
    }

    public function test_vehicle_details_table_can_be_rolled_back_and_recreated(): void
    {
        $migration = require database_path('migrations/2026_10_01_000003_add_general_order_details.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('order_vehicle_details'));
        $migration->up();
        $this->assertTrue(Schema::hasColumns('order_vehicle_details', ['order_id', 'color_id', 'fuel_type']));
    }
}
