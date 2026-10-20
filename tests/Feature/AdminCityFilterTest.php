<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCityFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_filter_applies_before_pagination_and_preserves_user_type(): void
    {
        $city = City::factory()->create();
        User::factory()->customer()->count(26)->create(['city_id' => $city->id]);
        User::factory()->customer()->count(3)->create();
        $provider = User::factory()->provider()->create(['city_id' => $city->id]);
        $this->actingAs(Admin::factory()->create(), 'sanctum');

        $this->getJson('/api/admin/users/customers?city_id='.$city->id)
            ->assertOk()->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 26)->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/admin/users/customers?city_id='.$city->id.'&page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.city.id', $city->id);
        $this->getJson('/api/admin/users/providers?city_id='.$city->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $provider->id);
        $this->getJson('/api/admin/users?city_id='.$city->id)
            ->assertOk()->assertJsonPath('meta.total', 27);
        $this->getJson('/api/admin/users/customers?city_id=')
            ->assertOk()->assertJsonPath('meta.total', 29);
    }

    public function test_city_combines_with_existing_filters_and_empty_results_are_paginated(): void
    {
        $city = City::factory()->create();
        $match = User::factory()->customer()->blocked()->create([
            'city_id' => $city->id, 'full_name' => 'Sara Match', 'mobile' => '+966511111111',
        ]);
        User::factory()->customer()->create(['city_id' => $city->id, 'full_name' => 'Sara Other']);
        User::factory()->customer()->blocked()->create(['full_name' => 'Sara Elsewhere']);
        $this->actingAs(Admin::factory()->create(), 'sanctum');

        $this->getJson('/api/admin/users/customers?city_id='.$city->id.'&name=Sara&mobile=111111&status=blocked')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        $empty = City::factory()->create();
        $this->getJson('/api/admin/users/customers?city_id='.$empty->id)
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }

    public function test_invalid_unknown_and_deleted_cities_are_rejected_with_localized_errors(): void
    {
        $deleted = City::factory()->create();
        $deleted->delete();
        $this->actingAs(Admin::factory()->create(), 'sanctum');
        foreach (['abc', '0', '-1', '1.5', '999999', (string) $deleted->id, '1&city_id[]=2'] as $id) {
            $this->getJson('/api/admin/users/customers?city_id='.$id)
                ->assertUnprocessable()->assertJsonValidationErrors('city_id');
        }
        foreach (['en', 'ar'] as $language) {
            $this->getJson('/api/admin/users/customers?city_id=999999', ['Accept-Language' => $language])
                ->assertUnprocessable()
                ->assertJsonPath('errors.city_id.0', trans('auth.validation.city_id.exists', [], $language));
        }
    }
}
