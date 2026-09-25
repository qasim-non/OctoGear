<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedProfileEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/profile')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_read_their_shared_profile(): void
    {
        $user = User::factory()->customer()->create();
        $token = $user->createToken('customer-mobile')->plainTextToken;

        $this->withToken($token)->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.type', 'customer')
            ->assertJsonPath('data.city.id', $user->city_id);
    }

    public function test_provider_can_read_their_shared_profile(): void
    {
        $user = User::factory()->provider()->create();
        $token = $user->createToken('provider-mobile')->plainTextToken;

        $this->withToken($token)->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.type', 'service provider');
    }

    public function test_blocked_user_cannot_read_their_shared_profile(): void
    {
        $user = User::factory()->customer()->blocked()->create();
        $token = $user->createToken('blocked-mobile')->plainTextToken;

        $this->withToken($token)->getJson('/api/profile')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }
}
