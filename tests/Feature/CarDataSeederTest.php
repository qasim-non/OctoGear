<?php

namespace Tests\Feature;

use Database\Seeders\CarDataSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CarDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_labels_are_localized_and_reruns_preserve_existing_records(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $companyId = DB::table('cars_companies')->insertGetId([
            'id' => 700, 'name_en' => 'Toyota', 'name_ar' => 'تويوتا',
            'country_id' => DB::table('countries')->where('name_en', 'Japan')->value('id'),
        ]);
        $nameId = DB::table('cars_names')->insertGetId([
            'id' => 900, 'car_company_id' => $companyId,
            'name_en' => 'Camry', 'name_ar' => 'كامري',
        ]);
        $modelId = DB::table('models')->insertGetId([
            'id' => 1200, 'car_name_id' => $nameId,
            'name_en' => 'Camry 2024', 'name_ar' => 'كامري 2024',
        ]);
        $customId = DB::table('cars_names')->insertGetId([
            'car_company_id' => $companyId,
            'name_en' => 'Operator custom car', 'name_ar' => 'اسم مخصص',
        ]);

        $this->seed(CarDataSeeder::class);

        $this->assertDatabaseHas('cars_names', [
            'id' => $nameId, 'car_company_id' => $companyId,
            'name_en' => 'Camry', 'name_ar' => 'Camry (كامري)',
        ]);
        $this->assertDatabaseHas('models', [
            'id' => $modelId, 'car_name_id' => $nameId,
            'name_en' => 'Camry 2024', 'name_ar' => 'Camry (كامري) 2024',
        ]);
        $this->assertDatabaseHas('models', [
            'car_name_id' => $nameId, 'name_en' => 'Camry', 'name_ar' => 'Camry (كامري)',
        ]);
        $this->assertDatabaseHas('cars_names', [
            'name_en' => 'ZDX', 'name_ar' => 'ZDX',
        ]);
        $this->assertDatabaseHas('cars_names', [
            'id' => $customId, 'name_en' => 'Operator custom car', 'name_ar' => 'اسم مخصص',
        ]);

        foreach (['en' => 'Camry', 'ar' => 'Camry (كامري)'] as $locale => $label) {
            $this->getJson("/api/reference/companies/{$companyId}/names?search=Camry", ['Accept-Language' => $locale])
                ->assertOk()->assertJsonFragment(['id' => $nameId, 'name' => $label]);
            $this->getJson("/api/reference/names/{$nameId}/models?search=2024", ['Accept-Language' => $locale])
                ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', $label.' 2024');
        }
        $this->getJson("/api/reference/companies/{$companyId}/names?search=".urlencode('كامري'), ['Accept-Language' => 'ar'])
            ->assertOk()->assertJsonFragment(['id' => $nameId, 'name' => 'Camry (كامري)']);

        $this->assertDatabaseHas('cars_companies', [
            'id' => $companyId, 'name_en' => 'Toyota', 'name_ar' => 'Toyota (تويوتا)',
        ]);
        foreach (['en' => 'Toyota', 'ar' => 'Toyota (تويوتا)'] as $locale => $label) {
            $this->getJson('/api/reference/companies?search=Toyota', ['Accept-Language' => $locale])
                ->assertOk()->assertJsonFragment(['id' => $companyId, 'name' => $label]);
        }
        $this->getJson('/api/reference/companies?search='.urlencode('تويوتا'), ['Accept-Language' => 'ar'])
            ->assertOk()->assertJsonFragment(['id' => $companyId, 'name' => 'Toyota (تويوتا)']);

        $this->assertDatabaseCount('cars_companies', 103);
        $this->assertDatabaseCount('cars_names', 1979); // Includes the operator's custom row.
        $this->assertDatabaseCount('models', 2399);
        foreach (['cars_companies', 'cars_names'] as $table) {
            $rows = DB::table($table)->get();
            foreach ($rows as $row) {
                if ($table === 'cars_names' && $row->id === $customId) {
                    continue;
                }
                $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $row->name_en, $table.': '.$row->name_en);
                $this->assertTrue(
                    $row->name_ar === $row->name_en || str_starts_with($row->name_ar, $row->name_en.' ('),
                    'Missing English designation in '.$table.': '.$row->name_en,
                );
            }
        }
        $names = DB::table('cars_names')->get()->keyBy('id');
        foreach (DB::table('models')->get() as $model) {
            $name = $names[$model->car_name_id];
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $model->name_en);
            $this->assertStringStartsWith($name->name_en, $model->name_en);
            $suffix = substr($model->name_en, strlen($name->name_en));
            $this->assertSame($name->name_ar.$suffix, $model->name_ar, $model->name_en);
        }

        $before = [];
        foreach (['cars_companies', 'cars_names', 'models'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $this->travel(1)->day();
        $this->seed(CarDataSeeder::class);
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->toJson(), $table.' changed on rerun');
        }
        $this->travelBack();
    }
}
