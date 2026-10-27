<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\AuthSession;
use App\Models\PersonalAccessToken;
use App\Models\RefreshToken;
use App\Models\User;
use App\Repositories\ActiveSessionChannelRepository;
use App\Repositories\AuthSessionRepository;
use App\Repositories\DeviceTokenRepository;
use App\Services\AdminCredentialService;
use App\Services\AdminUserService;
use App\Services\AuthSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_expires_but_refresh_keeps_the_login_and_absolute_deadline(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $pair = app(AuthSessionService::class)->start($user);
        $this->assertSame(600, $pair['expires_in']);
        $this->assertSame(now()->addDays(14)->toIso8601String(), $pair['session_expires_at']);
        $this->travel(10)->minutes();
        $this->freshToken($pair['token']);
        $this->getJson('/api/profile')->assertUnauthorized();
        $next = $this->refresh($pair)->assertOk()->json('data');
        $this->assertNotSame($pair['token'], $next['token']);
        $this->assertNotSame($pair['refresh_token'], $next['refresh_token']);
        $this->assertSame($pair['session_id'], $next['session_id']);
        $this->assertSame($pair['session_expires_at'], $next['session_expires_at']);
        $this->freshToken($next['token']);
        $this->getJson('/api/profile')->assertOk();
    }

    public function test_refresh_secret_is_hashed_and_exact_retry_receipt_is_encrypted(): void
    {
        $pair = app(AuthSessionService::class)->start(User::factory()->create());
        $id = (string) Str::uuid();
        $next = $this->refresh($pair, $id)->assertOk()->json('data');
        $this->assertSame($next, $this->refresh($pair, $id)->assertOk()->json('data'));
        $row = RefreshToken::firstOrFail();
        $this->assertSame(hash('sha256', $pair['refresh_token']), $row->token_hash);
        $this->assertStringNotContainsString($next['refresh_token'], $row->getRawOriginal('retry_response'));
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->assertDatabaseCount('refresh_tokens', 2);
    }

    public function test_reuse_revokes_only_the_compromised_login_and_cannot_be_rolled_back(): void
    {
        $user = User::factory()->create();
        $one = app(AuthSessionService::class)->start($user);
        $other = app(AuthSessionService::class)->start($user);
        $next = $this->refresh($one)->assertOk()->json('data');
        $this->refresh($one)->assertUnauthorized();
        $this->assertNotNull(AuthSession::findOrFail($one['session_id'])->revoked_at);
        $this->freshToken($next['token']);
        $this->getJson('/api/profile')->assertUnauthorized();
        $this->refresh($next)->assertUnauthorized();
        $this->freshToken($other['token']);
        $this->getJson('/api/profile')->assertOk();
    }

    public function test_forged_secret_does_not_revoke_a_real_login(): void
    {
        $pair = app(AuthSessionService::class)->start(User::factory()->create());
        $forged = $pair;
        $forged['refresh_token'] = explode('.', $pair['refresh_token'])[0].'.'.str_repeat('0', 64);
        $this->refresh($forged)->assertUnauthorized();
        $this->refresh($pair)->assertOk();
    }

    public function test_absolute_expiry_cannot_be_extended_and_caps_last_access_token(): void
    {
        $this->freezeTime();
        $pair = app(AuthSessionService::class)->start(User::factory()->create());
        $this->travel(14 * 24 * 60 - 2)->minutes();
        $next = $this->refresh($pair)->assertOk()->json('data');
        $this->assertSame(120, $next['expires_in']);
        $this->travel(2)->minutes();
        $this->refresh($next)->assertUnauthorized();
        $this->freshToken($next['token']);
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_logout_with_expired_access_revokes_refresh_and_push_binding(): void
    {
        $user = User::factory()->create();
        $pair = app(AuthSessionService::class)->start($user);
        $token = PersonalAccessToken::findToken($pair['token']);
        app(DeviceTokenRepository::class)->register($user, $token, ['token' => 'test-device', 'platform' => 'android', 'locale' => 'en']);
        $this->travel(11)->minutes();
        $this->postJson('/api/auth/session/logout', ['refresh_token' => $pair['refresh_token']])->assertOk();
        $this->assertDatabaseCount('device_tokens', 0);
        $this->refresh($pair)->assertUnauthorized();
        $this->postJson('/api/auth/session/logout', ['refresh_token' => $pair['refresh_token']])->assertOk();
    }

    public function test_channels_and_push_survive_rotation_and_cleanup_without_duplicates(): void
    {
        $user = User::factory()->create();
        $pair = app(AuthSessionService::class)->start($user);
        $anchor = PersonalAccessToken::findToken($pair['token']);
        app(DeviceTokenRepository::class)->register($user, $anchor, ['token' => 'test-device', 'platform' => 'android', 'locale' => 'en']);
        $this->travel(11)->minutes();
        $next = $this->refresh($pair)->assertOk()->json('data');
        $current = PersonalAccessToken::findToken($next['token']);
        app(DeviceTokenRepository::class)->register($user, $current, ['token' => 'test-device', 'platform' => 'android', 'locale' => 'en']);
        app(AuthSessionRepository::class)->prune();
        $this->assertSame($anchor->id, $current->channelId());
        $this->assertDatabaseHas('device_tokens', ['personal_access_token_id' => $anchor->id]);
        $this->assertCount(1, app(DeviceTokenRepository::class)->activeFor($user));
        $this->assertSame(['private-chat.sessions.'.$anchor->id], app(ActiveSessionChannelRepository::class)->channels($user, [$user->id], 'private-chat.sessions.'));
        $this->freshToken($next['token']);
        config(['chat_realtime.enabled' => true]);
        $this->getJson('/api/chat/realtime')->assertOk()->assertJsonPath('data.channel', 'private-chat.sessions.'.$anchor->id);
        $this->postJson('/api/auth/logout')->assertOk();
        $this->assertCount(0, app(DeviceTokenRepository::class)->activeFor($user));
    }

    public function test_block_then_unblock_does_not_resurrect_credentials(): void
    {
        $user = User::factory()->create();
        $pair = app(AuthSessionService::class)->start($user);
        app(AdminUserService::class)->setStatus($user, UserStatus::Blocked);
        app(AdminUserService::class)->setStatus($user, UserStatus::Unblocked);
        $this->refresh($pair)->assertUnauthorized();
        $this->freshToken($pair['token']);
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_admin_password_change_revokes_all_refresh_credentials(): void
    {
        $admin = Admin::factory()->create();
        $pair = app(AuthSessionService::class)->start($admin);
        app(AdminCredentialService::class)->changePassword($admin, 'password', 'New private password 2026!');
        $this->refresh($pair)->assertUnauthorized();
    }

    public function test_pending_admin_setup_issues_neither_credential(): void
    {
        $admin = Admin::factory()->create();
        $admin->forceFill(['must_change_password' => true, 'temporary_password_expires_at' => now()->addDay()])->save();
        $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.requires_password_change', true)
            ->assertJsonMissingPath('data.token')->assertJsonMissingPath('data.refresh_token');
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public function test_browser_refresh_is_cookie_only_origin_checked_and_keeps_fixed_expiry(): void
    {
        $admin = Admin::factory()->create();
        $this->withHeaders(['Origin' => 'https://admin.example.test', 'X-Auth-Client' => 'web']);
        config(['auth_sessions.browser_origins' => ['https://admin.example.test']]);
        $login = $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertOk()->assertJsonMissingPath('data.refresh_token')->assertHeader('Cache-Control', 'no-store, private');
        $cookie = $login->getCookie(config('auth_sessions.cookie_name'), false);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('/api/auth/admin', $cookie->getPath());
        $this->withCredentials()->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        $response = $this->postJson('/api/auth/admin/refresh', ['request_id' => (string) Str::uuid()])
            ->assertOk()->assertJsonMissingPath('data.refresh_token');
        $this->assertSame($login->json('data.session_expires_at'), $response->json('data.session_expires_at'));
        $this->withHeader('Origin', 'https://evil.example.test');
        $this->postJson('/api/auth/admin/refresh', ['request_id' => (string) Str::uuid()])->assertForbidden();
        $this->postJson('/api/auth/admin/logout')->assertForbidden();
    }

    public function test_cookie_login_requires_exact_origin_and_browser_tokens_cannot_refresh_as_mobile(): void
    {
        $admin = Admin::factory()->create();
        $this->withHeaders(['Origin' => 'https://evil.test', 'X-Auth-Client' => 'web']);
        $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertForbidden();
        $this->assertDatabaseCount('auth_sessions', 0);
        $pair = app(AuthSessionService::class)->start($admin, true);
        $this->refresh($pair)->assertUnauthorized();
        $this->assertNull(AuthSession::findOrFail($pair['session_id'])->revoked_at);
    }

    private function refresh(array $pair, ?string $requestId = null)
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/auth/refresh', ['refresh_token' => $pair['refresh_token'], 'request_id' => $requestId ?? (string) Str::uuid()]);
    }

    private function freshToken(string $token): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($token);
    }
}
