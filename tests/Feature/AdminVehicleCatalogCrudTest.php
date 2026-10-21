<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CarCompany;
use App\Models\CarModel;
use App\Models\CarName;
use App\Models\Color;
use App\Models\Country;
use App\Models\CustomerCar;
use App\Models\FuelType;
use App\Models\Order;
use App\Models\OrderVehicleDetail;
use App\Models\Store;
use App\Models\StoreCompany;
use App\Models\StoreRequest;
use App\Models\StoresCar;
use App\Models\User;
use App\Support\VehicleCatalogVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminVehicleCatalogCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_rename_and_soft_delete_a_company_and_model(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $country = Country::factory()->create();
        $companyData = ['name_en' => 'Octo Brand', 'name_ar' => 'شركة تجريبية', 'country_id' => $country->id];
        $company = $this->postJson('/api/admin/car-companies', $companyData)->assertCreated()->json('data');
        $this->assertSame($companyData['name_ar'], $company['name_ar']);
        $modelData = ['name_en' => 'Model One', 'name_ar' => 'الموديل الأول', 'car_company_id' => $company['id']];
        $model = $this->postJson('/api/admin/car-names', $modelData)->assertCreated()->json('data');
        $model = $this->patchJson('/api/admin/car-names/'.$model['id'], [...$modelData, 'name_en' => 'Renamed', 'version' => $model['version']])
            ->assertOk()->assertJsonPath('data.name_en', 'Renamed')->json('data');
        $company = $this->patchJson('/api/admin/car-companies/'.$company['id'], [...$companyData, 'name_ar' => 'الشركة المعدلة', 'version' => $company['version']])
            ->assertOk()->assertJsonPath('data.name_ar', 'الشركة المعدلة')->json('data');
        $this->deleteJson('/api/admin/car-companies/'.$company['id'], ['version' => $company['version']])->assertConflict();
        $variant = CarModel::create(['car_name_id' => $model['id'], 'name_en' => 'Variant', 'name_ar' => 'فئة']);
        $this->deleteJson('/api/admin/car-names/'.$model['id'], ['version' => $model['version']])->assertOk();
        $this->assertSoftDeleted('cars_names', ['id' => $model['id']]);
        $this->assertDatabaseHas('models', ['id' => $variant->id, 'deleted_at' => null]);
        $this->deleteJson('/api/admin/car-companies/'.$company['id'], ['version' => $company['version']])->assertOk();
        $this->assertSoftDeleted('cars_companies', ['id' => $company['id']]);
        $this->getJson('/api/admin/car-companies/options')->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/car-names')->assertJsonCount(0, 'data');
        $this->deleteJson('/api/admin/car-names/'.$model['id'], ['version' => $model['version']])->assertNotFound();
    }

    public function test_writes_require_admin_authorization(): void
    {
        $company = CarCompany::factory()->create();
        $name = CarName::factory()->create(['car_company_id' => $company->id]);
        foreach ([null, User::factory()->customer()->create(), User::factory()->provider()->create(), Admin::factory()->blocked()->create()] as $actor) {
            $this->app['auth']->forgetGuards();
            $this->withHeader('Authorization', $actor ? 'Bearer '.$actor->createToken('test')->plainTextToken : '');
            foreach (['car-companies' => $company, 'car-names' => $name] as $path => $record) {
                $body = ['name_en' => 'New', 'name_ar' => 'جديد', 'country_id' => null, 'car_company_id' => $company->id];
                $this->postJson('/api/admin/'.$path, $body)->assertStatus($actor ? 403 : 401);
                $this->patchJson('/api/admin/'.$path.'/'.$record->id, [...$body, 'version' => VehicleCatalogVersion::for($record)])->assertStatus($actor ? 403 : 401);
                $this->deleteJson('/api/admin/'.$path.'/'.$record->id, ['version' => VehicleCatalogVersion::for($record)])->assertStatus($actor ? 403 : 401);
            }
        }
        $this->assertDatabaseCount('cars_companies', 1);
        $this->assertDatabaseCount('cars_names', 1);
    }

    public function test_validation_duplicates_stale_edits_and_fixed_model_parent(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $data = ['name_en' => 'Toyota', 'name_ar' => 'تويوتا', 'country_id' => null];
        $company = $this->postJson('/api/admin/car-companies', $data)->assertCreated()->json('data');
        foreach (['en', 'ar'] as $locale) {
            $this->postJson('/api/admin/car-companies', [...$data, 'name_en' => 'toyota'], ['Accept-Language' => $locale])
                ->assertUnprocessable()->assertJsonPath('errors.name_en.0', __('admin_catalog.duplicate', [], $locale));
            $this->postJson('/api/admin/car-companies', [...$data, 'name_en' => '   '], ['Accept-Language' => $locale])
                ->assertUnprocessable()->assertJsonPath('errors.name_en.0', __('admin_catalog.name_invalid', [], $locale));
        }
        $this->postJson('/api/admin/car-companies', [...$data, 'name_en' => str_repeat('x', 101), 'country_id' => 99999])->assertUnprocessable()->assertJsonValidationErrors(['name_en', 'country_id']);
        $this->patchJson('/api/admin/car-companies/'.$company['id'], $data)->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->patchJson('/api/admin/car-companies/'.$company['id'], [...$data, 'name_en' => 'New name', 'version' => $company['version']])->assertOk();
        $this->patchJson('/api/admin/car-companies/'.$company['id'], [...$data, 'version' => $company['version']])->assertConflict();
        $this->deleteJson('/api/admin/car-companies/'.$company['id'], ['version' => $company['version']])->assertConflict();
        $modelData = ['name_en' => 'Camry', 'name_ar' => 'كامري', 'car_company_id' => $company['id']];
        $model = $this->postJson('/api/admin/car-names', $modelData)->assertCreated()->json('data');
        $this->postJson('/api/admin/car-names', $modelData)->assertUnprocessable();
        $other = CarCompany::factory()->create();
        $this->postJson('/api/admin/car-names', [...$modelData, 'car_company_id' => $other->id])->assertCreated();
        $this->patchJson('/api/admin/car-names/'.$model['id'], [...$modelData, 'car_company_id' => $other->id, 'version' => $model['version']])->assertUnprocessable()->assertJsonValidationErrors('car_company_id');
        $other->delete();
        $this->postJson('/api/admin/car-names', [...$modelData, 'car_company_id' => $other->id])->assertUnprocessable();
    }

    public function test_deletion_preserves_used_vehicles_and_store_associations_even_when_archived(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $name = CarName::factory()->create();
        $car = CustomerCar::factory()->create(['car_name_id' => $name->id, 'color_id' => Color::factory()->create()->id, 'fuel_type' => FuelType::factory()->create()->id, 'customer_id' => User::factory()->customer()->create()->id]);
        $car->delete();
        $this->deleteJson('/api/admin/car-names/'.$name->id, ['version' => VehicleCatalogVersion::for($name)])->assertConflict();
        $this->assertDatabaseHas('cars_names', ['id' => $name->id, 'deleted_at' => null]);
        $name->delete();
        $parent = $name->carCompany;
        $this->deleteJson('/api/admin/car-companies/'.$parent->id, ['version' => VehicleCatalogVersion::for($parent)])->assertConflict();
        $stockName = CarName::factory()->create();
        $stock = StoresCar::factory()->create(['car_name_id' => $stockName->id]);
        $this->deleteJson('/api/admin/car-names/'.$stockName->id, ['version' => VehicleCatalogVersion::for($stockName)])->assertConflict();
        $this->assertDatabaseHas('stores_cars', ['id' => $stock->id, 'deleted_at' => null]);
        $company = CarCompany::factory()->create();
        StoreCompany::create(['company_id' => $company->id, 'store_id' => Store::factory()->create()->id])->delete();
        $this->deleteJson('/api/admin/car-companies/'.$company->id, ['version' => VehicleCatalogVersion::for($company)])->assertConflict();
        $pendingCompany = CarCompany::factory()->create();
        StoreRequest::factory()->create(['company_ids' => [$pendingCompany->id]]);
        $this->deleteJson('/api/admin/car-companies/'.$pendingCompany->id, ['version' => VehicleCatalogVersion::for($pendingCompany)])->assertConflict();
    }

    public function test_country_options_are_authenticated_complete_bilingual_and_exclude_deleted(): void
    {
        Country::factory()->count(30)->create();
        Country::factory()->create()->delete();
        $this->getJson('/api/admin/catalog-countries/options')->assertUnauthorized();
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->getJson('/api/admin/catalog-countries/options')->assertOk()->assertJsonCount(30, 'data')
            ->assertJsonStructure(['data' => [['id', 'name_en', 'name_ar']]])->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_order_snapshots_are_not_rewritten_by_renames_and_block_deletion(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $name = CarName::factory()->create();
        $snapshot = OrderVehicleDetail::forceCreate([
            'order_id' => Order::factory()->create(['customer_id' => User::factory()->customer()->create()->id])->id,
            'car_name_id' => $name->id, 'car_company_id' => $name->car_company_id,
            'car_name_en' => $name->name_en, 'car_name_ar' => $name->name_ar,
            'company_name_en' => 'Snapshot brand', 'company_name_ar' => 'شركة محفوظة',
            'color_name_en' => 'White', 'color_name_ar' => 'أبيض', 'fuel_type_en' => 'Petrol', 'fuel_type_ar' => 'بنزين',
            'manufacturing_year' => 2020,
        ]);
        $updated = $this->patchJson('/api/admin/car-names/'.$name->id, [
            'name_en' => 'Updated name', 'name_ar' => 'اسم معدّل', 'car_company_id' => $name->car_company_id,
            'version' => VehicleCatalogVersion::for($name),
        ])->assertOk()->json('data');
        $this->assertSame($name->name_en, $snapshot->fresh()->car_name_en);
        $this->deleteJson('/api/admin/car-names/'.$name->id, ['version' => $updated['version']])->assertConflict();
        $name->delete();
        $company = $name->carCompany;
        $this->deleteJson('/api/admin/car-companies/'.$company->id, ['version' => VehicleCatalogVersion::for($company)])->assertConflict();
    }
}
