<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Jobs\BroadcastSupportUpdate;
use App\Models\Admin;
use App\Models\SupportConversation;
use App\Models\User;
use App\Repositories\SupportSessionRepository;
use App\Services\Push\PushNotificationPayload;
use App\Services\SupportChatService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    private function login(User|Admin $actor)
    {
        $token = $actor->createToken('support-test');
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken);

        return $token->accessToken;
    }

    private function payload(string $text = 'Help please'): array
    {
        return ['content' => $text, 'client_message_id' => (string) Str::uuid()];
    }

    private function start(User $user): array
    {
        $this->login($user);

        return $this->postJson('/api/support/conversation/messages', $this->payload())->assertOk()->json('data');
    }

    public function test_preview_is_read_only_and_both_roles_keep_one_conversation(): void
    {
        $this->getJson('/api/support/conversation')->assertUnauthorized();
        $user = User::factory()->customer()->create();
        $this->login($user);
        $this->getJson('/api/support/conversation')->assertOk()->assertJsonPath('data', null);
        $this->assertDatabaseCount('support_conversations', 0);
        $first = $this->start($user);
        $user->update(['type' => UserType::ServiceProvider]);
        $second = $this->start($user);
        $this->assertSame($first['conversation']['id'], $second['conversation']['id']);
        $this->assertDatabaseCount('support_conversations', 1);
        $this->assertDatabaseCount('support_messages', 2);
        $this->getJson('/api/support/conversation')->assertOk()->assertJsonPath('data.unread_count', 0)->assertJsonMissingPath('data.user');
    }

    public function test_retries_are_idempotent_and_changed_content_conflicts(): void
    {
        $this->login(User::factory()->create());
        $payload = $this->payload();
        $a = $this->postJson('/api/support/conversation/messages', $payload)->assertOk()->json('data.message.id');
        $this->postJson('/api/support/conversation/messages', $payload)->assertOk()->assertJsonPath('data.message.id', $a);
        $payload['content'] = 'Changed';
        $this->postJson('/api/support/conversation/messages', $payload)->assertConflict();
        $this->assertDatabaseCount('support_messages', 1);
    }

    public function test_support_reply_badge_read_boundaries_and_team_read(): void
    {
        $user = User::factory()->create();
        $first = $this->start($user);
        $id = $first['conversation']['id'];
        $admin = Admin::factory()->create();
        $this->login($admin);
        $this->getJson('/api/admin/support/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 1);
        $this->patchJson("/api/admin/support/conversations/$id/read", ['through_id' => $first['message']['id']])->assertOk()->assertJsonPath('data.unread_count', 0);
        $a = $this->postJson("/api/admin/support/conversations/$id/messages", $this->payload('First reply'))->assertOk()->json('data.message.id');
        $this->postJson("/api/admin/support/conversations/$id/messages", $this->payload('Second reply'))->assertOk();
        $this->assertSame(2, $user->notifications()->count());
        $notification = $user->notifications()->first();
        $data = app(PushNotificationPayload::class)->data($user, $notification->id, $notification->data);
        $this->assertSame('support_reply', $data['type']);
        $this->assertArrayNotHasKey('content', $data);
        $this->login($user);
        $this->getJson('/api/support/conversation')->assertOk()->assertJsonPath('data.unread_count', 2);
        $this->patchJson('/api/support/conversation/read', ['through_id' => $a])->assertOk()->assertJsonPath('data.unread_count', 1);
        $this->patchJson('/api/support/conversation/read', ['through_id' => $first['message']['id']])->assertOk()->assertJsonPath('data.unread_count', 1);
        $this->patchJson('/api/support/conversation/read', ['through_id' => 999999])->assertUnprocessable();
    }

    public function test_close_blocks_new_sends_preserves_retry_and_explicit_reopen_keeps_history(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        $payload = $this->payload();
        $first = $this->postJson('/api/support/conversation/messages', $payload)->assertOk()->json('data');
        $id = $first['conversation']['id'];
        $version = $first['conversation']['version'];
        $this->login(Admin::factory()->create());
        $closed = $this->patchJson("/api/admin/support/conversations/$id/status", ['status' => 'closed', 'version' => $version])->assertOk()->assertJsonPath('data.status', 'closed')->json('data');
        $this->getJson('/api/admin/support/conversations')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/support/conversations?status=closed')->assertOk()->assertJsonCount(1, 'data');
        $this->login($user);
        $this->getJson('/api/support/conversation/timeline')->assertOk()->assertJsonCount(1, 'data.messages');
        $this->postJson('/api/support/conversation/messages', $payload)->assertOk();
        $this->postJson('/api/support/conversation/messages', $this->payload())->assertConflict();
        $this->patchJson('/api/support/conversation/reopen', ['version' => $version])->assertConflict();
        $this->patchJson('/api/support/conversation/reopen', ['version' => $closed['version']])->assertOk()->assertJsonPath('data.status', 'open');
        $this->postJson('/api/support/conversation/messages', $this->payload())->assertOk();
        $this->assertDatabaseCount('support_conversations', 1);
        $this->assertDatabaseCount('support_conversation_events', 2);
    }

    public function test_new_message_prevents_a_stale_admin_closure(): void
    {
        $user = User::factory()->create();
        $first = $this->start($user);
        $this->postJson('/api/support/conversation/messages', $this->payload('Still need help'))->assertOk();
        $this->login(Admin::factory()->create());
        $this->patchJson('/api/admin/support/conversations/'.$first['conversation']['id'].'/status', ['status' => 'closed', 'version' => $first['conversation']['version']])->assertConflict();
    }

    public function test_users_cannot_access_admin_or_foreign_chats_and_blocked_actors_are_rejected(): void
    {
        $first = $this->start(User::factory()->create());
        $other = User::factory()->create();
        $this->login($other);
        $this->getJson('/api/admin/support/conversations')->assertForbidden();
        $this->getJson('/api/support/conversation')->assertJsonPath('data', null);
        $this->postJson('/api/support/conversation/messages', $this->payload() + ['user_id' => 1, 'sender_kind' => 'admin'])->assertOk()->assertJsonPath('data.message.sender_kind', 'user');
        $this->patchJson('/api/support/conversation/read', ['through_id' => $first['message']['id']])->assertUnprocessable();
        $other->update(['status' => 'blocked']);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/support/conversation')->assertForbidden();
        $this->login(Admin::factory()->blocked()->create());
        $this->getJson('/api/admin/support/conversations')->assertForbidden();
    }

    public function test_validation_and_forward_pagination_do_not_skip_messages(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        $this->postJson('/api/support/conversation/messages', $this->payload(' '))->assertUnprocessable();
        $this->postJson('/api/support/conversation/messages', $this->payload(str_repeat('x', 2001)))->assertUnprocessable();
        $this->postJson('/api/support/conversation/messages', ['content' => 'Hello'])->assertUnprocessable();
        for ($i = 0; $i < 43; $i++) {
            app(SupportChatService::class)->send($user, $this->payload("Message $i"));
        }
        $first = $this->getJson('/api/support/conversation/timeline?after_id=0')->assertOk()->assertJsonCount(40, 'data.messages')->assertJsonPath('data.has_more', true)->json('data.messages');
        $this->assertSame('Message 39', $first[0]['content']);
        $this->getJson('/api/support/conversation/timeline?after_id='.$first[0]['id'])->assertOk()->assertJsonCount(3, 'data.messages')->assertJsonPath('data.has_more', false);
        $this->getJson('/api/support/conversation/timeline?after_id=0&before_id=5')->assertUnprocessable();
    }

    public function test_admin_filters_and_session_socket_authorization(): void
    {
        $this->start(User::factory()->provider()->create(['full_name' => 'Support Provider']));
        $admin = Admin::factory()->create();
        $token = $this->login($admin);
        $this->getJson('/api/admin/support/conversations?type=customer')->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/support/conversations?search=Support')->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/support/conversations?status=invalid')->assertUnprocessable();
        config(['chat_realtime.enabled' => true, 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        $channel = 'private-support.admin.sessions.'.$token->id;
        $this->getJson('/api/admin/support/realtime')->assertOk()->assertJsonPath('data.channel', $channel)->assertJsonMissingPath('data.secret');
        $this->postJson('/api/admin/support/realtime/auth', ['socket_id' => '12.34', 'channel_name' => $channel])->assertOk();
        $this->postJson('/api/admin/support/realtime/auth', ['socket_id' => '12.34', 'channel_name' => 'private-support.admin.sessions.99999'])->assertForbidden();
        $token->delete();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/support/realtime')->assertUnauthorized();
    }

    public function test_socket_delivery_is_after_commit_and_omits_revoked_sessions(): void
    {
        config(['chat_realtime.enabled' => true]);
        Queue::fake();
        $user = User::factory()->create();
        $first = $this->start($user);
        Queue::assertPushed(BroadcastSupportUpdate::class, fn ($job) => $job->afterCommit === true && $job->queue === 'realtime');
        $admin = Admin::factory()->create();
        $token = $this->login($admin);
        $expired = $admin->createToken('expired', ['*'], now()->subMinute())->accessToken;
        $this->assertSame(['private-support.admin.sessions.'.$token->id], app(SupportSessionRepository::class)->adminChannels());
        $token->delete();
        $this->assertSame([], app(SupportSessionRepository::class)->adminChannels());
        $mock = \Mockery::mock();
        Broadcast::shouldReceive('connection')->with('reverb')->andReturn($mock);
        $mock->shouldReceive('broadcast')->once()->withArgs(fn ($channels, $event, $payload) => $event === 'support.updated' && ! isset($payload['conversation']['user']) && $payload['message']['content'] === 'Help please');
        app()->call([new BroadcastSupportUpdate($first['conversation']['id'], 'message', $first['message']['id']), 'handle']);
    }

    public function test_database_rejects_duplicate_account_conversations(): void
    {
        $user = User::factory()->create();
        $this->start($user);
        $this->expectException(QueryException::class);
        SupportConversation::create(['user_id' => $user->id]);
    }
}
