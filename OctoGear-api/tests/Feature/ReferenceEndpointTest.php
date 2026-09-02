<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferenceEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_cities_index_returns_seeded_cities(): void
    {
        DB::table('countries')->insert(['name_en' => 'Saudi Arabia', 'name_ar' => 'المملكة العربية السعودية']);
        $countryId = DB::table('countries')->value('id');

        DB::table('cities')->insert([
            ['name_en' => 'Riyadh', 'name_ar' => 'الرياض', 'country_id' => $countryId],
            ['name_en' => 'Jeddah', 'name_ar' => 'جدة', 'country_id' => $countryId],
        ]);

        $this->getJson('/api/reference/cities')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    public function test_companies_index_returns_data(): void
    {
        DB::table('cars_companies')->insert([
            ['name_en' => 'Toyota', 'name_ar' => 'تويوتا', 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Honda', 'name_ar' => 'هوندا', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->getJson('/api/reference/companies')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_company_names_returns_names_for_a_company(): void
    {
        $companyId = DB::table('cars_companies')->insertGetId([
            'name_en' => 'Toyota', 'name_ar' => 'تويوتا',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('cars_names')->insert([
            ['name_en' => 'Corolla', 'name_ar' => 'كورولا', 'car_company_id' => $companyId, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Camry', 'name_ar' => 'كامي', 'car_company_id' => $companyId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->getJson("/api/reference/companies/{$companyId}/names")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_company_names_returns_empty_for_unknown_company(): void
    {
        $this->getJson('/api/reference/companies/999999/names')
            ->assertStatus(404);
    }

    public function test_car_name_models_returns_models(): void
    {
        $companyId = DB::table('cars_companies')->insertGetId([
            'name_en' => 'Toyota', 'name_ar' => 'تويوتا',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $nameId = DB::table('cars_names')->insertGetId([
            'name_en' => 'Corolla', 'name_ar' => 'كورولا',
            'car_company_id' => $companyId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('models')->insert([
            ['name_en' => 'Corolla 2023', 'name_ar' => 'كورولا 2023', 'car_name_id' => $nameId, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Corolla 2024', 'name_ar' => 'كورولا 2024', 'car_name_id' => $nameId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->getJson("/api/reference/names/{$nameId}/models")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_fuel_types_index_returns_data(): void
    {
        DB::table('fuel_types')->insert([
            ['type_en' => 'Gasoline', 'type_ar' => 'بنزين', 'created_at' => now(), 'updated_at' => now()],
            ['type_en' => 'Diesel', 'type_ar' => 'ديزل', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->getJson('/api/reference/fuel-types')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_colors_index_returns_data(): void
    {
        DB::table('colors')->insert([
            ['name_en' => 'Black', 'name_ar' => 'أسود', 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'White', 'name_ar' => 'أبيض', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->getJson('/api/reference/colors')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_sections_index_returns_data(): void
    {
        DB::table('car_sections')->insert([
            ['name_en' => 'Engine', 'name_ar' => 'المحرك', 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Brakes', 'name_ar' => 'الفرامل', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->getJson('/api/reference/sections')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_section_components_returns_components(): void
    {
        $sectionId = DB::table('car_sections')->insertGetId([
            'name_en' => 'Engine', 'name_ar' => 'المحرك',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('components')->insert([
            ['name_en' => 'Piston', 'name_ar' => 'المكبس', 'section_id' => $sectionId, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Spark Plug', 'name_ar' => 'شمعة الإشعال', 'section_id' => $sectionId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->getJson("/api/reference/sections/{$sectionId}/components")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_fuel_type_name_is_localized_via_accept_language_header(): void
    {
        DB::table('fuel_types')->insert([
            ['type_en' => 'Electric', 'type_ar' => 'كهرباء', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $en = $this->getJson('/api/reference/fuel-types', ['Accept-Language' => 'en'])
            ->assertOk()
            ->json('data.0.name');

        $ar = $this->getJson('/api/reference/fuel-types', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->json('data.0.name');

        $this->assertSame('Electric', $en);
        $this->assertSame('كهرباء', $ar);
    }
}