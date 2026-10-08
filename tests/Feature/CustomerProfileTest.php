<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_updates_only_name_and_city_and_receives_localized_profile(): void
    {
        $user = User::factory()->customer()->create(['full_name' => 'Old Name']);
        $other = User::factory()->customer()->create();
        $city = City::factory()->create(['name_en' => 'Riyadh', 'name_ar' => 'الرياض']);
        $token = $user->createToken('profile-test')->plainTextToken;

        $this->withToken($token)->withHeader('Accept-Language', 'en')
            ->patchJson('/api/customer/profile', [
                'full_name' => 'Updated Name',
                'city_id' => $city->id,
                'mobile' => '+966500000099',
                'type' => 'service provider',
                'id' => $other->id,
            ])->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.full_name', 'Updated Name')
            ->assertJsonPath('data.city.id', $city->id)
            ->assertJsonPath('data.city.name', 'Riyadh')
            ->assertJsonPath('data.mobile', $user->mobile)
            ->assertJsonPath('data.type', 'customer');

        $this->assertSame($user->mobile, $user->fresh()->mobile);
        $this->assertSame($other->full_name, $other->fresh()->full_name);
        $this->withToken($token)->withHeader('Accept-Language', 'ar')
            ->getJson('/api/profile')->assertOk()
            ->assertJsonPath('data.full_name', 'Updated Name')
            ->assertJsonPath('data.city.name', 'الرياض');
    }

    public function test_invalid_name_and_city_do_not_change_the_profile(): void
    {
        $user = User::factory()->customer()->create();
        $token = $user->createToken('profile-test')->plainTextToken;
        foreach ([['full_name' => ''], ['full_name' => str_repeat('A', 101)], ['city_id' => 999999]] as $payload) {
            $this->withToken($token)->patchJson('/api/customer/profile', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors(array_keys($payload));
        }
        $this->assertSame($user->full_name, $user->fresh()->full_name);
        $this->assertSame($user->city_id, $user->fresh()->city_id);
    }

    public function test_customer_profile_update_requires_an_active_customer(): void
    {
        $this->patchJson('/api/customer/profile', ['full_name' => 'Updated Name'])->assertUnauthorized();
        foreach ([User::factory()->provider()->create(), User::factory()->customer()->blocked()->create()] as $user) {
            $token = $user->createToken('profile-test')->plainTextToken;
            $this->withToken($token)->patchJson('/api/customer/profile', ['full_name' => 'Updated Name'])
                ->assertForbidden();
        }
    }
}
