<?php

namespace Tests\Feature;

use App\Http\Resources\AdminCarCompanyResource;
use App\Http\Resources\AdminCarNameResource;
use App\Models\Admin;
use App\Models\CarCompany;
use App\Models\CarName;
use App\Models\Country;
use App\Models\User;
use App\Services\AdminVehicleCatalogQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminVehicleCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_directories_require_an_allowed_admin_and_have_no_write_routes(): void
    {
        foreach (['car-companies', 'car-companies/options', 'car-names'] as $path) {
            $this->app['auth']->forgetGuards();
            $this->withHeader('Authorization', '')->getJson('/api/admin/'.$path)->assertUnauthorized();
            foreach ([User::factory()->customer()->create(), User::factory()->provider()->create(), Admin::factory()->blocked()->create()] as $actor) {
                $this->app['auth']->forgetGuards();
                $this->withToken($actor->createToken('test')->plainTextToken)->getJson('/api/admin/'.$path)->assertForbidden();
            }
        }
        $this->app['auth']->forgetGuards();
        $this->withToken(Admin::factory()->create()->createToken('test', ['admin'])->plainTextToken)
            ->getJson('/api/admin/car-companies')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/api/admin/car-companies', ['name_en' => 'Unexpected'])->assertStatus(405);
        $this->assertDatabaseCount('cars_companies', 0);
    }

    public function test_companies_paginate_search_both_languages_and_count_only_available_names(): void
    {
        $country = Country::factory()->create(['name_en' => 'Japan', 'name_ar' => 'اليابان']);
        $toyota = CarCompany::factory()->create(['name_en' => 'Toyota', 'name_ar' => 'تويوتا', 'country_id' => $country->id]);
        CarName::factory()->count(2)->create(['car_company_id' => $toyota->id]);
        CarName::factory()->create(['car_company_id' => $toyota->id])->delete();
        CarCompany::factory()->create(['name_en' => 'Toyota removed'])->delete();
        for ($i = 0; $i < 26; $i++) {
            CarCompany::factory()->create(['name_en' => sprintf('Brand %02d', $i)]);
        }
        $this->actingAs(Admin::factory()->create(), 'admin');
        $first = $this->getJson('/api/admin/car-companies', ['Accept-Language' => 'en'])
            ->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 27)->assertJsonPath('meta.per_page', 25);
        $second = $this->getJson('/api/admin/car-companies?page=2', ['Accept-Language' => 'en'])->assertOk()->assertJsonCount(2, 'data');
        $this->assertEmpty(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        $this->getJson('/api/admin/car-companies?search=0')->assertOk()->assertJsonPath('meta.total', 12);
        foreach (['Toyota', 'تويوتا'] as $search) {
            $this->getJson('/api/admin/car-companies?search='.urlencode($search), ['Accept-Language' => 'en'])
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0', [
                    'id' => $toyota->id, 'name' => 'Toyota', 'country' => ['id' => $country->id, 'name' => 'Japan'], 'models_count' => 2,
                ]);
        }
        $this->getJson('/api/admin/car-companies?search=Toyota', ['Accept-Language' => 'ar'])
            ->assertOk()->assertJsonPath('data.0.name', 'تويوتا')->assertJsonPath('data.0.country.name', 'اليابان');
        $country->delete();
        $this->getJson('/api/admin/car-companies?search=Toyota')->assertOk()->assertJsonPath('data.0.country', null);
    }

    public function test_models_combine_company_and_bilingual_search_and_exclude_removed_parents(): void
    {
        $country = Country::factory()->create(['name_en' => 'Japan', 'name_ar' => 'اليابان']);
        $toyota = CarCompany::factory()->create(['name_en' => 'Toyota', 'name_ar' => 'تويوتا', 'country_id' => $country->id]);
        $other = CarCompany::factory()->create();
        $model = CarName::factory()->create(['car_company_id' => $toyota->id, 'name_en' => 'Camry 0', 'name_ar' => 'كامري']);
        CarName::factory()->create(['car_company_id' => $other->id, 'name_en' => 'Camry 0', 'name_ar' => 'كامري']);
        CarName::factory()->create(['car_company_id' => $toyota->id, 'name_en' => 'Removed'])->delete();
        $removed = CarCompany::factory()->create();
        CarName::factory()->create(['car_company_id' => $removed->id]);
        $removed->delete();
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->getJson('/api/admin/car-names')->assertOk()->assertJsonPath('meta.total', 2);
        foreach (['Camry', 'كامري', '0'] as $search) {
            $this->getJson('/api/admin/car-names?company_id='.$toyota->id.'&search='.urlencode($search), ['Accept-Language' => 'en'])
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0', [
                    'id' => $model->id, 'name' => 'Camry 0', 'company' => [
                        'id' => $toyota->id, 'name' => 'Toyota', 'country' => ['id' => $country->id, 'name' => 'Japan'],
                    ],
                ])->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->getJson('/api/admin/car-names?company_id='.$toyota->id, ['Accept-Language' => 'ar'])
            ->assertOk()->assertJsonPath('data.0.name', 'كامري')->assertJsonPath('data.0.company.name', 'تويوتا')
            ->assertJsonPath('data.0.company.country.name', 'اليابان');
        $this->getJson('/api/admin/car-names?company_id='.$removed->id)->assertUnprocessable();
        $this->getJson('/api/admin/car-names?search=missing')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    }

    public function test_model_pages_are_stable_with_equal_names(): void
    {
        $company = CarCompany::factory()->create();
        $models = CarName::factory()->count(27)->create(['car_company_id' => $company->id, 'name_en' => 'Same']);
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->getJson('/api/admin/car-names')->assertOk()->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.per_page', 25)->assertJsonPath('data.0.id', $models[0]->id);
        $this->getJson('/api/admin/car-names?page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $models[25]->id);
    }

    public function test_company_options_are_complete_bilingual_and_do_not_expose_extra_fields(): void
    {
        CarCompany::factory()->count(55)->create();
        CarCompany::factory()->create()->delete();
        $this->actingAs(Admin::factory()->create(), 'admin');
        $response = $this->getJson('/api/admin/car-companies/options')->assertOk()->assertJsonCount(55, 'data')
            ->assertJsonMissingPath('meta')->assertHeader('Cache-Control', 'no-store, private');
        foreach ($response->json('data') as $company) {
            $this->assertEqualsCanonicalizing(['id', 'name_en', 'name_ar'], array_keys($company));
        }
    }

    public function test_invalid_filters_use_the_localized_validation_envelope(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        foreach (['en', 'ar'] as $locale) {
            foreach (['page=0', 'page=1.5', 'search[]=invalid', 'search='.str_repeat('a', 101)] as $query) {
                $this->getJson('/api/admin/car-companies?'.$query, ['Accept-Language' => $locale])
                    ->assertUnprocessable()->assertJsonPath('success', false)->assertJsonStructure(['message', 'errors']);
            }
            $this->getJson('/api/admin/car-names?company_id=99999', ['Accept-Language' => $locale])
                ->assertUnprocessable()->assertJsonPath('errors.company_id.0', __('admin_catalog.filters_invalid', [], $locale));
        }
    }

    public function test_resources_do_not_query_while_serializing(): void
    {
        $company = CarCompany::factory()->create(['country_id' => Country::factory()->create()->id]);
        CarName::factory()->count(3)->create(['car_company_id' => $company->id]);
        $query = app(AdminVehicleCatalogQueryService::class);
        $companies = $query->companies([]);
        $models = $query->names([]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $request = Request::create('/');
        AdminCarCompanyResource::collection($companies->items())->response($request)->getData(true);
        AdminCarNameResource::collection($models->items())->response($request)->getData(true);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }
}
