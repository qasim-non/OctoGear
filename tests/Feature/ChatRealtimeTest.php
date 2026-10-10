<?php

namespace Tests\Feature;

use App\Jobs\BroadcastChatUpdate;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Repositories\ChatSessionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ChatRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['chat_realtime.enabled' => true, 'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        Queue::fake();
    }

    private function login(User $user)
    {
        $token = $user->createToken('app');
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken);

        return $token->accessToken;
    }

    private function conversation(): Conversation
    {
        return Conversation::factory()->create([
            'customer_id' => User::factory()->customer()->create()->id,
            'provider_id' => User::factory()->provider()->create()->id,
        ]);
    }

    public function test_configuration_and_channel_auth_are_bound_to_current_session(): void
    {
        $this->getJson('/api/chat/realtime')->assertUnauthorized();
        $user = User::factory()->create();
        $other = $user->createToken('another-device')->accessToken;
        $session = $this->login($user);
        $channel = 'private-chat.sessions.'.$session->id;
        $this->getJson('/api/chat/realtime')->assertOk()->assertJsonPath('data.channel', $channel)
            ->assertJsonPath('data.enabled', true)->assertJsonMissingPath('data.secret');
        $this->postJson('/api/chat/realtime/auth', ['channel_name' => $channel, 'socket_id' => '12.34'])
            ->assertOk()->assertJsonPath('data.auth', 'test-key:'.hash_hmac('sha256', '12.34:'.$channel, 'test-secret'));
        foreach (['private-chat.sessions.'.$other->id, 'private-chat.sessions.99999', 'public-chat', null, []] as $wrong) {
            $this->postJson('/api/chat/realtime/auth', ['channel_name' => $wrong, 'socket_id' => '12.34'])->assertForbidden();
        }
        $this->postJson('/api/chat/realtime/auth', ['channel_name' => $channel, 'socket_id' => 'invalid'])->assertUnprocessable();
        $user->update(['status' => 'blocked']);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/chat/realtime')->assertForbidden();
    }

    public function test_message_send_queues_once_and_read_receipts_only_queue_when_rows_change(): void
    {
        $chat = $this->conversation();
        $this->login($chat->customer);
        $payload = ['content' => 'Hello', 'client_message_id' => (string) Str::uuid()];
        $id = $this->postJson("/api/conversations/{$chat->id}/messages", $payload)->assertCreated()->json('data.id');
        $this->postJson("/api/conversations/{$chat->id}/messages", $payload)->assertCreated();
        Queue::assertPushed(BroadcastChatUpdate::class, 1);
        Queue::assertPushed(BroadcastChatUpdate::class, fn ($job) => $job->afterCommit === true
            && $job->queue === 'realtime' && $job->messageId === $id);
        $this->login($chat->provider);
        $this->patchJson("/api/conversations/{$chat->id}/read", ['through_id' => $id])->assertOk();
        $this->patchJson("/api/conversations/{$chat->id}/read", ['through_id' => $id])->assertOk();
        Queue::assertPushed(BroadcastChatUpdate::class, 2);
        Queue::assertPushed(BroadcastChatUpdate::class, fn ($job) => $job->readerId === $chat->provider_id && $job->throughId === $id);
        $this->login($chat->customer);
        $this->getJson("/api/conversations/{$chat->id}/timeline?after_id={$id}")
            ->assertOk()->assertJsonPath('data.read_through_id', $id)->assertJsonCount(0, 'data.messages');
    }

    public function test_revoked_session_cannot_reauthorize(): void
    {
        $session = $this->login(User::factory()->customer()->create());
        $session->delete();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/chat/realtime/auth', [
            'channel_name' => 'private-chat.sessions.'.$session->id, 'socket_id' => '12.34',
        ])->assertUnauthorized();
    }

    public function test_delivery_rechecks_session_expiry_revocation_and_participants(): void
    {
        $chat = $this->conversation();
        $customerSession = $chat->customer->createToken('app')->accessToken;
        $providerSession = $chat->provider->createToken('app')->accessToken;
        $revoked = $chat->customer->createToken('revoked')->accessToken;
        $revoked->delete();
        $chat->customer->createToken('expired', ['*'], now()->subMinute());
        User::factory()->create()->createToken('outsider');
        $message = Message::factory()->create(['conversation_id' => $chat->id, 'sender_id' => $chat->customer_id, 'is_read' => false]);
        $driver = Mockery::mock();
        Broadcast::shouldReceive('connection')->once()->with('reverb')->andReturn($driver);
        $driver->shouldReceive('broadcast')->once()->withArgs(function ($channels, $event, $payload) use ($customerSession, $providerSession, $message) {
            $this->assertEqualsCanonicalizing(['private-chat.sessions.'.$customerSession->id, 'private-chat.sessions.'.$providerSession->id], $channels);
            $this->assertSame('chat.updated', $event);
            $this->assertSame($message->content, $payload['message']['content']);
            $this->assertSame($message->id, $payload['message']['id']);
            $this->assertSame($message->conversation_id, $payload['conversation']['id']);
            $this->assertSame($message->sender_id, $payload['conversation']['customer_id']);
            $this->assertSame(1, $payload['conversation']['provider_unread']);
            $this->assertSame(0, $payload['conversation']['customer_unread']);
            $this->assertArrayHasKey('snapshot_at', $payload);
            $this->assertArrayNotHasKey('token', $payload);

            return true;
        });
        app()->call([new BroadcastChatUpdate($chat->id, $message->id), 'handle']);
    }

    public function test_blocked_accounts_and_deleted_messages_do_not_receive_broadcast_content(): void
    {
        $chat = $this->conversation();
        $chat->customer->createToken('app');
        $providerSession = $chat->provider->createToken('app')->accessToken;
        $chat->customer->update(['status' => 'blocked']);
        $this->assertSame(['private-chat.sessions.'.$providerSession->id], app(ChatSessionRepository::class)->channelsFor($chat));
        $message = Message::factory()->create(['conversation_id' => $chat->id, 'sender_id' => $chat->customer_id, 'is_read' => false]);
        $message->delete();
        Broadcast::shouldReceive('connection')->never();
        app()->call([new BroadcastChatUpdate($chat->id, $message->id), 'handle']);
    }
}
