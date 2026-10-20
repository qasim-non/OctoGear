<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCityOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receives_the_complete_bilingual_catalog_without_deleted_cities(): void
    {
        City::factory()->count(55)->create();
        $first = City::factory()->create(['name_en' => 'AAA City', 'name_ar' => 'مدينة أولى']);
        $deleted = City::factory()->create(['name_en' => 'AAA Deleted']);
        $deleted->delete();
        $token = Admin::factory()->create()->createToken('test', ['admin'])->plainTextToken;

        foreach (['en', 'ar'] as $language) {
            $this->app['auth']->forgetGuards();
            $response = $this->withToken($token)->getJson('/api/admin/cities/options', ['Accept-Language' => $language])
                ->assertOk()->assertJsonCount(56, 'data')->assertJsonMissingPath('meta')
                ->assertJsonPath('data.0', ['id' => $first->id, 'name_en' => 'AAA City', 'name_ar' => 'مدينة أولى'])
                ->assertHeader('Cache-Control', 'no-store, private');
            $this->assertNotContains($deleted->id, array_column($response->json('data'), 'id'));
            foreach ($response->json('data') as $city) {
                $this->assertEqualsCanonicalizing(['id', 'name_en', 'name_ar'], array_keys($city));
            }
        }
    }

    public function test_options_require_an_allowed_administrator(): void
    {
        $this->getJson('/api/admin/cities/options')->assertUnauthorized();
        foreach ([User::factory()->customer()->create(), User::factory()->provider()->create(), Admin::factory()->blocked()->create()] as $identity) {
            $this->app['auth']->forgetGuards();
            $this->withToken($identity->createToken('test')->plainTextToken)
                ->getJson('/api/admin/cities/options')->assertForbidden();
        }
    }

    public function test_empty_catalog_returns_an_empty_list(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->getJson('/api/admin/cities/options')->assertOk()->assertJsonPath('data', []);
    }
}
