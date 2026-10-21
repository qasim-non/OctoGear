<?php

namespace Tests\Feature;

use App\Jobs\SendCustomerPush;
use App\Models\Admin;
use App\Models\DeviceToken;
use App\Models\SupportConversation;
use App\Models\User;
use App\Repositories\DeviceTokenRepository;
use App\Services\Push\FcmSender;
use App\Services\SupportChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_push_is_localized_private_and_skips_a_reply_already_read(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        config(['push.enabled' => true]);
        $user = User::factory()->provider()->create();
        $session = $user->createToken('support-app');
        $this->withToken($session->plainTextToken)->postJson('/api/push/device', [
            'token' => 'fake-support-device', 'locale' => 'ar', 'platform' => 'android',
        ])->assertOk();
        $service = app(SupportChatService::class);
        $first = $service->send($user, ['content' => 'Help', 'client_message_id' => (string) Str::uuid()]);
        $reply = $service->send(Admin::factory()->create(), [
            'content' => 'Private support response', 'client_message_id' => (string) Str::uuid(),
        ], $first['conversation']);
        Queue::assertPushed(SendCustomerPush::class, 1);
        Queue::assertPushed(SendCustomerPush::class, fn ($job) => $job->afterCommit === true);
        $device = DeviceToken::firstOrFail();
        $job = new SendCustomerPush($user->notifications()->firstOrFail()->id, $user->id, $device->id, $session->accessToken->id);
        $sender = $this->mock(FcmSender::class);
        $sender->shouldReceive('send')->once()->withArgs(fn ($target, $data, $body) => $target->id === $device->id && $data['type'] === 'support_reply'
            && $data['support_message_id'] === (string) $reply['message']->id
            && $body === trans('push.support_reply', [], 'ar')
            && ! str_contains(json_encode($data), 'Private support response'))->andReturn(true);
        $job->handle(app(DeviceTokenRepository::class), $sender);
        $service->read($user, SupportConversation::firstOrFail(), $reply['message']->id);
        $job->handle(app(DeviceTokenRepository::class), $sender);
        Http::assertNothingSent();
    }
}
