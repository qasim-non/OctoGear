<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_token_reads_admin_profile_and_dashboard_and_is_revoked_by_logout(): void
    {
        $admin = Admin::factory()->create();
        $token = $this->postJson('/api/auth/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/admin/profile')->assertOk()
            ->assertJsonPath('data.employee_id', $admin->employee_id)
            ->assertJsonPath('data.email', $admin->email)
            ->assertJsonPath('data.assigned_role', $admin->assigned_role->value)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.mobile')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/admin/dashboard')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/admin/profile')->assertUnauthorized();
    }

    public function test_customer_and_provider_tokens_cannot_access_admin_identity(): void
    {
        foreach ([User::factory()->customer()->create(), User::factory()->provider()->create()] as $user) {
            $this->app['auth']->forgetGuards();
            $token = $user->createToken('test')->plainTextToken;
            $this->withToken($token)->getJson('/api/admin/profile')->assertForbidden();
        }
    }

    public function test_admin_token_does_not_authenticate_mobile_profile_routes(): void
    {
        $token = Admin::factory()->create()->createToken('test', ['admin'])->plainTextToken;
        $this->withToken($token)->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_customer_token_still_reads_mobile_profile_and_can_log_out(): void
    {
        $user = User::factory()->customer()->create();
        $token = $user->createToken('test')->plainTextToken;
        $this->withToken($token)->getJson('/api/profile')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_blocked_admin_and_missing_credentials_cannot_read_profile(): void
    {
        $this->getJson('/api/admin/profile')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $token = Admin::factory()->blocked()->create()->createToken('test', ['admin'])->plainTextToken;
        $this->withToken($token)->getJson('/api/admin/profile')->assertForbidden();
    }
}
