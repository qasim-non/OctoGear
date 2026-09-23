<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_user_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_get_their_current_profile(): void
    {
        $user = User::factory()->customer()->create();
        $token = $user->createToken('customer-mobile')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.type', 'customer')
            ->assertJsonPath('data.city.id', $user->city_id);
    }

    public function test_provider_can_get_their_current_profile(): void
    {
        $user = User::factory()->provider()->create();
        $token = $user->createToken('provider-mobile')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.type', 'service provider');
    }

    public function test_blocked_user_is_forbidden_from_reading_current_profile(): void
    {
        $user = User::factory()->customer()->blocked()->create();
        $token = $user->createToken('blocked-mobile')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_logout_revokes_only_the_current_personal_access_token(): void
    {
        $user = User::factory()->customer()->create();
        $currentToken = $user->createToken('current-mobile');
        $otherToken = $user->createToken('other-mobile');

        $this->withToken($currentToken->plainTextToken)->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);

        $this->withToken($otherToken->plainTextToken)->getJson('/api/auth/me')
            ->assertOk();
    }

    public function test_logout_is_safe_for_a_transient_test_token(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
