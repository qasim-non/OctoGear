<?php

namespace Tests\Feature;

use App\Exceptions\PushDeliveryException;
use App\Jobs\SendCustomerPush;
use App\Models\DeviceToken;
use App\Models\Message;
use App\Models\OrderOffer;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Notifications\NewOfferNotification;
use App\Repositories\DeviceTokenRepository;
use App\Services\Push\FcmAccessToken;
use App\Services\Push\FcmSender;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerPushTest extends TestCase
{
    use RefreshDatabase;

    private function login(?User $user = null): array
    {
        $user ??= User::factory()->customer()->create();
        $session = $user->createToken('customer-app');
        $this->app['auth']->forgetGuards();
        $this->withToken($session->plainTextToken);

        return [$user, $session->accessToken];
    }

    private function register(string $token = 'fcm-test-token', string $locale = 'en')
    {
        return $this->postJson('/api/push/device', compact('token', 'locale') + ['platform' => 'android']);
    }

    public function test_registration_rotation_logout_and_multiple_devices(): void
    {
        [$user, $first] = $this->login();
        $this->register()->assertOk()->assertJsonPath('data', null);
        $this->register()->assertOk();
        $this->assertDatabaseCount('device_tokens', 1);
        $this->register('rotated', 'ar')->assertOk();
        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-test-token']);
        [, $second] = $this->login($user);
        $this->register('second')->assertOk();
        $this->assertDatabaseCount('device_tokens', 2);
        $this->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseHas('device_tokens', ['personal_access_token_id' => $first->id]);
        $this->assertDatabaseMissing('device_tokens', ['personal_access_token_id' => $second->id]);
        $this->assertArrayNotHasKey('token', DeviceToken::first()->toArray());
    }

    public function test_device_reassignment_and_unregister_are_scoped_to_the_current_session(): void
    {
        [$oldUser, $oldSession] = $this->login();
        $this->register()->assertOk();
        [$newUser, $newSession] = $this->login();
        $this->register()->assertOk();
        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['user_id' => $newUser->id, 'personal_access_token_id' => $newSession->id]);
        $oldSession->delete();
        $this->assertDatabaseCount('device_tokens', 1);
        $this->deleteJson('/api/push/device')->assertOk();
        $this->deleteJson('/api/push/device')->assertOk();
        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_authorization_validation_and_locale(): void
    {
        $this->register()->assertUnauthorized();
        $this->login(User::factory()->provider()->create());
        $this->register()->assertForbidden();
        $this->login();
        $this->withHeader('Accept-Language', 'ar')->register(str_repeat('x', 513))->assertUnprocessable()
            ->assertJsonPath('errors.token.0', trans('push.invalid', [], 'ar'));
        $this->postJson('/api/push/device', ['token' => 'valid', 'platform' => 'ios', 'locale' => 'xx'])
            ->assertUnprocessable()->assertJsonValidationErrors(['platform', 'locale']);
        $this->login(User::factory()->customer()->create(['status' => 'blocked']));
        $this->register()->assertForbidden();
    }

    public function test_existing_inbox_events_queue_once_after_commit_without_contacting_firebase(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        config(['push.enabled' => true]);
        [$user] = $this->login();
        $this->register()->assertOk();
        $offer = new OrderOffer(['order_id' => 17, 'price' => 20]);
        $offer->id = 42;
        $user->notify(new NewOfferNotification($offer));
        $message = new Message(['conversation_id' => 7, 'sender_id' => 99]);
        $message->id = 88;
        $user->notify(new NewMessageNotification($message));
        $this->assertDatabaseCount('notifications', 2);
        Queue::assertPushed(SendCustomerPush::class, 2);
        Queue::assertPushed(SendCustomerPush::class, fn ($job) => $job->afterCommit === true && $job->queue === 'push');
        Http::assertNothingSent();
    }

    public function test_disabled_push_does_not_change_the_inbox(): void
    {
        Queue::fake();
        config(['push.enabled' => false]);
        [$user] = $this->login();
        $this->register()->assertOk();
        $user->notify(new NewMessageNotification(new Message(['conversation_id' => 7, 'sender_id' => 99])));
        $this->assertDatabaseCount('notifications', 1);
        Queue::assertNothingPushed();
    }

    private function delivery(): array
    {
        config(['push.enabled' => true]);
        [$user, $session] = $this->login();
        $this->register()->assertOk();
        $device = DeviceToken::first();
        $id = (string) Str::uuid();
        $user->notifications()->create(['id' => $id, 'type' => NewOfferNotification::class,
            'data' => ['type' => 'new_offer', 'order_id' => 17, 'offer_id' => 42, 'price' => 999, 'message' => 'private']]);
        $this->mock(FcmAccessToken::class)->shouldReceive('get')->andReturn('oauth-test');

        return [new SendCustomerPush($id, $user->id, $device->id, $session->id), $device, $user, $session];
    }

    private function send(SendCustomerPush $job): void
    {
        $job->handle(app(DeviceTokenRepository::class), app(FcmSender::class));
    }

    public function test_fcm_payload_is_minimal_localized_and_navigation_safe(): void
    {
        [$job] = $this->delivery();
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'sent'])]);
        $this->send($job);
        Http::assertSent(function ($request) use ($job) {
            $message = $request['message'];

            return $message['data'] === ['notification_id' => $job->notificationId, 'recipient_id' => (string) $job->userId,
                'type' => 'new_offer', 'order_id' => '17', 'offer_id' => '42']
                && $message['notification']['body'] === trans('auth.notifications.new_offer', [], 'en')
                && $message['android']['notification']['tag'] === $job->notificationId
                && ! str_contains(json_encode($message), 'private');
        });
    }

    public function test_queued_push_cannot_follow_a_device_to_another_account(): void
    {
        [$job] = $this->delivery();
        $this->login();
        $this->register()->assertOk();
        Http::fake();
        $this->send($job);
        Http::assertNothingSent();
    }

    public function test_revoked_expired_stale_and_read_deliveries_are_skipped(): void
    {
        [$job, $device, $user, $session] = $this->delivery();
        Http::fake();
        $session->update(['expires_at' => now()->subMinute()]);
        $this->send($job);
        $session->update(['expires_at' => null]);
        $device->update(['last_seen_at' => now()->subDays(61)]);
        $this->send($job);
        $device->update(['last_seen_at' => now()]);
        $user->notifications()->update(['read_at' => now()]);
        $this->send($job);
        $user->notifications()->update(['read_at' => null]);
        $session->delete();
        $this->send($job);
        Http::assertNothingSent();
    }

    public function test_only_explicit_unregistered_responses_delete_tokens(): void
    {
        [$job] = $this->delivery();
        Http::fake(['*' => Http::response(['error' => ['details' => [[
            '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED',
        ]]]], 404)]);
        $this->send($job);
        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_transient_errors_are_retryable_and_do_not_expose_tokens(): void
    {
        [$job] = $this->delivery();
        Http::fake(['*' => Http::response(['error' => 'fcm-test-token'], 503)]);
        try {
            $this->send($job);
            $this->fail('Delivery should retry.');
        } catch (\RuntimeException $error) {
            $this->assertStringNotContainsString('fcm-test-token', $error->getMessage());
            $this->assertStringContainsString('503', $error->getMessage());
        }
        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertSame([60, 120, 300], $job->backoff());
        $this->assertStringNotContainsString('fcm-test-token', serialize($job));
    }

    public function test_payload_errors_do_not_delete_device_tokens_or_retry_forever(): void
    {
        [$job] = $this->delivery();
        Http::fake(['*' => Http::response(['error' => 'invalid payload'], 400)]);
        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('fail')->once()->with(\Mockery::on(fn ($error) => $error instanceof PushDeliveryException && ! $error->retryable()));
        $job->setJob($queueJob);
        $this->send($job);
        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function test_rate_limited_delivery_honors_retry_after(): void
    {
        [$job] = $this->delivery();
        Http::fake(['*' => Http::response([], 429, ['Retry-After' => '180'])]);
        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(180);
        $job->setJob($queueJob);
        $this->send($job);
        $this->assertDatabaseCount('device_tokens', 1);
    }
}
