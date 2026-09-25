<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogoutEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_only_the_current_access_token(): void
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
    }

    public function test_logout_is_safe_for_a_transient_sanctum_token(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
