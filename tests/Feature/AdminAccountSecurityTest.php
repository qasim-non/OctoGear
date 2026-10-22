<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;
use App\Repositories\SupportSessionRepository;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function token(Admin $admin): string
    {
        $this->app['auth']->forgetGuards();
        $token = $admin->createToken('test', ['admin'])->plainTextToken;
        $this->withToken($token);

        return $token;
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['name' => 'New colleague', 'email' => 'colleague@example.test', 'mobile' => '0501234567',
            'assigned_role' => 'developer', 'preferred_language' => 'ar', 'password' => 'Temporary-123456!', 'current_password' => 'password'], $extra);
    }

    private function createAccount(): Admin
    {
        $this->token(Admin::factory()->create());
        $id = $this->postJson('/api/admin/accounts', $this->payload())->assertCreated()
            ->assertJsonPath('data.must_change_password', true)->assertJsonMissingPath('data.password')
            ->assertHeader('Cache-Control', 'no-store, private')->json('data.employee_id');

        return Admin::findOrFail($id);
    }

    public function test_creation_hashes_password_and_requires_setup_before_any_token_is_issued(): void
    {
        $admin = $this->createAccount();
        $this->assertTrue(Hash::check('Temporary-123456!', $admin->password));
        $this->assertSame('+966501234567', $admin->mobile);
        $this->assertSame('ar', $admin->preferred_language->value);
        $this->withHeader('Authorization', '');
        $this->app['auth']->forgetGuards();
        $count = DB::table('personal_access_tokens')->count();
        $response = $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'Temporary-123456!'])
            ->assertOk()->assertJsonPath('data.requires_password_change', true)->assertJsonMissingPath('data.token');
        $challenge = $response->json('data.challenge');
        $this->assertSame($count, DB::table('personal_access_tokens')->count());
        $this->assertDatabaseMissing('admin_password_challenges', ['token_hash' => $challenge]);
        $this->withToken($challenge)->getJson('/api/admin/profile')->assertUnauthorized();
        $result = $this->postJson('/api/auth/admin/password-setup', ['challenge' => $challenge,
            'password' => 'My-own-password-2026!', 'password_confirmation' => 'My-own-password-2026!'])->assertOk();
        $this->assertFalse($admin->fresh()->must_change_password);
        $this->assertTrue(Hash::check('My-own-password-2026!', $admin->fresh()->password));
        $this->app['auth']->forgetGuards();
        $this->withToken($result->json('data.token'))->getJson('/api/admin/profile')->assertOk()
            ->assertJsonPath('data.permissions', ['catalog.view', 'catalog.manage']);
        $this->postJson('/api/auth/admin/password-setup', ['challenge' => $challenge,
            'password' => 'My-own-password-2026!', 'password_confirmation' => 'My-own-password-2026!'])->assertUnprocessable();
        $this->assertStringNotContainsString('Temporary-123456!', DB::table('admin_security_events')->get()->toJson());
    }

    public function test_challenge_expires_and_setup_rejects_reusing_temporary_password(): void
    {
        $admin = $this->createAccount();
        $challenge = $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'Temporary-123456!'])->json('data.challenge');
        $this->postJson('/api/auth/admin/password-setup', ['challenge' => $challenge, 'password' => 'Temporary-123456!', 'password_confirmation' => 'Temporary-123456!'])
            ->assertUnprocessable()->assertJsonValidationErrors('password')->assertJsonPath('success', false);
        $this->travel(11)->minutes();
        $this->postJson('/api/auth/admin/password-setup', ['challenge' => $challenge, 'password' => 'Different-123456!', 'password_confirmation' => 'Different-123456!'])->assertUnprocessable();
        $this->assertTrue($admin->fresh()->must_change_password);
        $this->travel(1)->days();
        $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'Temporary-123456!'])->assertForbidden();
    }

    public function test_role_matrix_guards_direct_requests_and_inactive_or_pending_accounts(): void
    {
        $paths = ['dashboard.view' => 'dashboard', 'users.view' => 'users/customers', 'stores.view' => 'stores',
            'requests.view' => 'store-requests', 'orders.view' => 'orders', 'payments.view' => 'payments',
            'catalog.view' => 'car-companies', 'admins.view' => 'accounts', 'support.access' => 'support/conversations'];
        foreach (AdminRole::cases() as $role) {
            $this->travel(61)->seconds();
            $admin = Admin::factory()->create(['assigned_role' => $role]);
            $this->token($admin);
            foreach ($paths as $permission => $path) {
                $this->getJson('/api/admin/'.$path)->assertStatus(in_array($permission, AdminPermissions::forRole($role), true) ? 200 : 403);
            }
        }
        foreach (['inactive', 'blocked'] as $status) {
            $admin = Admin::factory()->create(['status' => $status]);
            $this->token($admin);
            $this->getJson('/api/admin/profile')->assertForbidden();
            $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertForbidden();
        }
        $this->token(Admin::factory()->create(['must_change_password' => true]));
        $this->getJson('/api/admin/profile')->assertForbidden();
    }

    public function test_edits_are_versioned_reauthenticated_and_revoke_tokens_and_challenges(): void
    {
        $actor = Admin::factory()->create();
        $target = Admin::factory()->create(['assigned_role' => AdminRole::Employee]);
        $target->createToken('old', ['admin']);
        $this->token($actor);
        $data = $this->payload(['version' => 1, 'status' => 'inactive']);
        unset($data['password']);
        $this->patchJson('/api/admin/accounts/'.$target->getKey(), array_replace($data, ['current_password' => 'wrong']))->assertUnprocessable();
        $this->patchJson('/api/admin/accounts/'.$target->getKey(), $data)->assertOk()->assertJsonPath('data.version', 2);
        $this->assertSame(0, $target->tokens()->count());
        $this->patchJson('/api/admin/accounts/'.$target->getKey(), $data)->assertConflict();
        $this->patchJson('/api/admin/accounts/'.$actor->getKey(), $data)->assertUnprocessable();
    }

    public function test_personal_password_change_requires_current_password_and_revokes_all_sessions(): void
    {
        $admin = Admin::factory()->create();
        $token = $this->token($admin);
        $admin->createToken('second', ['admin']);
        $data = ['current_password' => 'wrong', 'password' => 'New-password-2026!', 'password_confirmation' => 'New-password-2026!'];
        $this->patchJson('/api/admin/profile/password', $data)->assertUnprocessable();
        $data['current_password'] = 'password';
        $this->patchJson('/api/admin/profile/password', $data)->assertOk();
        $this->assertSame(0, $admin->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/admin/profile')->assertUnauthorized();
    }

    public function test_non_managers_cannot_create_reset_archive_or_escalate_themselves(): void
    {
        $target = Admin::factory()->create();
        foreach ([AdminRole::Manager, AdminRole::Employee, AdminRole::Developer, AdminRole::Hr] as $role) {
            $this->travel(61)->seconds();
            $actor = Admin::factory()->create(['assigned_role' => $role]);
            $this->token($actor);
            $this->postJson('/api/admin/accounts', $this->payload(['assigned_role' => 'admin']))->assertForbidden();
            $this->patchJson('/api/admin/accounts/'.$actor->getKey(), $this->payload())->assertForbidden();
            $this->postJson('/api/admin/accounts/'.$target->getKey().'/reset-password', $this->payload())->assertForbidden();
            $this->deleteJson('/api/admin/accounts/'.$target->getKey(), ['version' => 1, 'current_password' => 'password'])->assertForbidden();
        }
    }

    public function test_read_only_staff_cannot_write_business_records_or_read_financial_details(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['customer_id' => $user->id]);
        Payment::factory()->create(['order_id' => $order->id]);
        $this->token(Admin::factory()->create(['assigned_role' => AdminRole::Employee]));
        $this->patchJson('/api/admin/users/'.$user->id.'/status', ['status' => 'blocked'])->assertForbidden();
        $this->postJson('/api/admin/orders/'.$order->id.'/cancel')->assertForbidden();
        $this->getJson('/api/admin/orders/'.$order->id)->assertOk()->assertJsonMissingPath('data.payment');
    }

    public function test_reset_replaces_existing_challenge_and_archive_keeps_audit_and_email_reserved(): void
    {
        $target = $this->createAccount();
        $challenge = $this->postJson('/api/auth/admin/login', ['email' => $target->email, 'password' => 'Temporary-123456!'])->json('data.challenge');
        $this->postJson('/api/admin/accounts/'.$target->getKey().'/reset-password', ['version' => 1, 'current_password' => 'password', 'password' => 'Replacement-1234!'])
            ->assertOk()->assertJsonPath('data.version', 2);
        $this->postJson('/api/auth/admin/password-setup', ['challenge' => $challenge, 'password' => 'Different-123456!', 'password_confirmation' => 'Different-123456!'])->assertUnprocessable();
        $this->deleteJson('/api/admin/accounts/'.$target->getKey(), ['version' => 2, 'current_password' => 'password'])->assertOk();
        $this->assertSoftDeleted($target);
        $this->assertDatabaseHas('admin_security_events', ['admin_id' => $target->getKey(), 'action' => 'archived']);
        $this->postJson('/api/admin/accounts', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_support_delivery_excludes_roles_without_permission_and_accounts_awaiting_setup(): void
    {
        $allowed = Admin::factory()->create(['assigned_role' => AdminRole::Employee]);
        $allowedToken = $allowed->createToken('test', ['admin']);
        Admin::factory()->create(['assigned_role' => AdminRole::Developer])->createToken('test', ['admin']);
        Admin::factory()->create(['must_change_password' => true])->createToken('test', ['admin']);
        $channels = app(SupportSessionRepository::class)->adminChannels();
        $this->assertSame(['private-support.admin.sessions.'.$allowedToken->accessToken->id], $channels);
    }

    public function test_directory_is_paginated_and_passwords_are_never_serialized(): void
    {
        $this->token(Admin::factory()->create(['assigned_role' => AdminRole::Hr]));
        Admin::factory()->count(26)->create(['name' => 'Search colleague', 'assigned_role' => AdminRole::Developer]);
        $this->getJson('/api/admin/accounts?search=Search&role=developer&status=active')->assertOk()->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 26)->assertJsonMissingPath('data.0.password');
        $this->getJson('/api/admin/accounts?search=Search&page=2')->assertOk()->assertJsonCount(1, 'data');
        Admin::factory()->create(['name' => 'Team 0', 'email' => 'zero@example.test', 'mobile' => '+966511111111', 'status' => 'inactive']);
        Admin::factory()->create(['name' => 'Team Alpha', 'email' => 'alpha@example.test', 'mobile' => '+966511111111', 'status' => 'inactive']);
        $this->getJson('/api/admin/accounts?search=0&status=inactive')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Team 0');
    }

    public function test_password_setup_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/admin/password-setup', ['challenge' => str_repeat('a', 64), 'password' => 'New-password-2026!', 'password_confirmation' => 'New-password-2026!'])->assertUnprocessable();
        }
        $this->postJson('/api/auth/admin/password-setup', [])->assertTooManyRequests();
    }

    public function test_password_bounds_and_role_input_are_validated_in_both_languages(): void
    {
        $this->token(Admin::factory()->create());
        foreach (['en', 'ar'] as $language) {
            $this->travel(61)->seconds();
            foreach (['short', str_repeat('ع', 40)] as $password) {
                $response = $this->postJson('/api/admin/accounts', $this->payload(['password' => $password]), ['Accept-Language' => $language])
                    ->assertUnprocessable()->assertJsonPath('success', false)->assertJsonValidationErrors('password');
                $this->assertSame(__('admin_accounts.password_length', [], $language), $response->json('errors.password.0'));
            }
            $this->postJson('/api/admin/accounts', $this->payload(['assigned_role' => 'superuser']), ['Accept-Language' => $language])->assertUnprocessable();
        }
    }

    public function test_role_updates_apply_to_existing_bearers_and_last_admin_cannot_archive_self(): void
    {
        $owner = Admin::factory()->create();
        $target = Admin::factory()->create(['assigned_role' => AdminRole::Manager]);
        $oldToken = $target->createToken('old', ['admin'])->plainTextToken;
        $this->token($owner);
        $payload = $this->payload(['version' => 1, 'status' => 'active']);
        unset($payload['password']);
        $this->patchJson('/api/admin/accounts/'.$target->getKey(), $payload)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($oldToken)->getJson('/api/admin/payments')->assertUnauthorized();
        $this->token($owner);
        $this->deleteJson('/api/admin/accounts/'.$owner->getKey(), ['version' => 1, 'current_password' => 'password'])->assertUnprocessable();
        $this->assertNotSoftDeleted($owner);
    }

    public function test_media_paths_do_not_bypass_role_checks(): void
    {
        $store = Store::factory()->create();
        $request = StoreRequest::factory()->create();
        $this->token(Admin::factory()->create(['assigned_role' => AdminRole::Developer]));
        $this->getJson('/api/media/stores/'.$store->id.'/registration')->assertForbidden();
        $this->getJson('/api/media/store-requests/'.$request->id.'/registration')->assertForbidden();
    }

    public function test_all_business_admin_routes_declare_a_permission(): void
    {
        $selfAndReference = ['api/admin/profile', 'api/admin/profile/language', 'api/admin/profile/password', 'api/admin/cities/options'];
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/admin/') && ! in_array($route->uri(), $selfAndReference, true)) {
                $this->assertTrue(collect($route->gatherMiddleware())->contains(fn ($middleware) => str_starts_with($middleware, 'admin.permission:')), $route->uri());
            }
        }
    }
}
