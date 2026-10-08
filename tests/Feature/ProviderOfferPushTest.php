<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Jobs\SendCustomerPush;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Repositories\DeviceTokenRepository;
use App\Services\Push\FcmAccessToken;
use App\Services\Push\FcmSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProviderOfferPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_an_offer_queues_customer_push_and_worker_sends_it_to_firebase(): void
    {
        config(['push.enabled' => true]);
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'test-message'])]);
        $this->mock(FcmAccessToken::class)->shouldReceive('get')->once()->andReturn('test-oauth');

        $customer = User::factory()->customer()->create();
        $session = $customer->createToken('customer-app');
        $device = $customer->deviceTokens()->create([
            'token' => 'test-customer-device',
            'platform' => 'android',
            'personal_access_token_id' => $session->accessToken->id,
            'locale' => 'en',
            'last_seen_at' => now(),
        ]);
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Pending,
        ]);

        $offerId = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/orders/{$order->id}/offer", [
                'store_id' => $store->id, 'price' => 30000,
            ])->assertCreated()->json('data.id');

        $notification = $customer->notifications()->sole();
        $this->assertSame($offerId, $notification->data['offer_id']);
        $this->assertSame($order->id, $notification->data['order_id']);
        Queue::assertPushed(SendCustomerPush::class, 1);
        $job = Queue::pushed(SendCustomerPush::class)->sole();
        $this->assertSame($customer->id, $job->userId);
        $this->assertSame($device->id, $job->deviceId);
        $this->assertSame($notification->id, $job->notificationId);
        $this->assertSame('push', $job->queue);
        $this->assertTrue($job->afterCommit);
        Http::assertNothingSent();

        $job->handle(app(DeviceTokenRepository::class), app(FcmSender::class));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['message']['token'] === 'test-customer-device'
            && $request['message']['data']['type'] === 'new_offer'
            && $request['message']['data']['offer_id'] === (string) $offerId
            && $request['message']['data']['order_id'] === (string) $order->id
            && $request['message']['data']['recipient_id'] === (string) $customer->id);
    }
}
