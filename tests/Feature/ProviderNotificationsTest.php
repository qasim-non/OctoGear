<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Events\MessageSent;
use App\Events\OrderCompleted;
use App\Events\OrderPaid;
use App\Jobs\SendCustomerPush;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use App\Notifications\OfferAcceptedNotification;
use App\Notifications\OrderCompletedNotification;
use App\Notifications\OrderPaidNotification;
use App\Notifications\StoreRequestReviewedNotification;
use App\Repositories\DeviceTokenRepository;
use App\Services\AdminStoreRequestService;
use App\Services\OrderService;
use App\Services\Push\FcmAccessToken;
use App\Services\Push\FcmSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['push.enabled' => false]);
    }

    private function row(User $user, array $attributes = [])
    {
        return $user->notifications()->create([...[
            'id' => (string) Str::uuid(), 'type' => OfferAcceptedNotification::class,
            'data' => ['type' => 'offer_accepted', 'order_id' => 17, 'offer_id' => 42],
            'created_at' => now()->subDay(),
        ], ...$attributes]);
    }

    public function test_opened_page_read_is_bounded_idempotent_and_preserves_unseen_arrivals_and_unread_cursor(): void
    {
        $user = User::factory()->provider()->create();
        for ($i = 0; $i < 25; $i++) {
            $this->row($user);
        }
        $this->actingAs($user, 'sanctum');
        $first = $this->getJson('/api/notifications/inbox?unread=1')->assertOk()->assertJsonCount(20, 'data.items');
        $ids = array_column($first->json('data.items'), 'id');
        $new = $this->row($user, ['created_at' => now()]);
        $this->patchJson('/api/notifications/read-page', ['ids' => $ids])->assertOk()
            ->assertJsonCount(20, 'data.ids')->assertJsonPath('data.unread_count', 6);
        $at = $user->notifications()->find($ids[0])->read_at;
        $this->travel(1)->minutes();
        $this->patchJson('/api/notifications/read-page', ['ids' => $ids])->assertOk()->assertJsonPath('data.unread_count', 6);
        $this->assertTrue($at->equalTo($user->notifications()->find($ids[0])->read_at));
        $next = $this->getJson('/api/notifications/inbox?'.http_build_query(['unread' => 1, 'cursor' => $first->json('data.next_cursor')]))
            ->assertOk()->assertJsonCount(5, 'data.items')->assertJsonPath('data.next_cursor', null);
        $this->patchJson('/api/notifications/read-page', ['ids' => array_column($next->json('data.items'), 'id')])
            ->assertOk()->assertJsonPath('data.unread_count', 1);
        $this->assertNull($new->fresh()->read_at);
    }

    public function test_page_reads_require_auth_and_validate_size_uuid_and_distinct_ids(): void
    {
        $this->patchJson('/api/notifications/read-page', ['ids' => [(string) Str::uuid()]])->assertUnauthorized();
        $this->actingAs(User::factory()->provider()->create(), 'sanctum');
        foreach ([[], ['bad'], array_fill(0, 2, (string) Str::uuid()), array_map(fn () => (string) Str::uuid(), range(1, 21))] as $ids) {
            $this->patchJson('/api/notifications/read-page', compact('ids'))->assertUnprocessable();
        }
    }

    public function test_page_reads_reject_foreign_missing_and_wrong_morph_ids_atomically(): void
    {
        $user = User::factory()->provider()->create();
        $own = $this->row($user);
        $foreign = $this->row(User::factory()->provider()->create());
        $wrongMorph = $this->row($user);
        $wrongMorph->update(['notifiable_type' => 'another_model']);
        $this->actingAs($user, 'sanctum');
        foreach ([$foreign->id, $wrongMorph->id, (string) Str::uuid()] as $id) {
            $this->patchJson('/api/notifications/read-page', ['ids' => [$own->id, $id]])->assertNotFound();
            $this->assertNull($own->fresh()->read_at);
        }
        $this->assertNull($foreign->fresh()->read_at);
        $this->assertNull($wrongMorph->fresh()->read_at);
    }

    private function offer(): OrderOffer
    {
        return OrderOffer::factory()->create([
            'order_id' => Order::factory()->general()->create(['status' => OrderStatus::Pending, 'customer_id' => User::factory()->customer()->create()->id])->id,
            'store_id' => Store::factory()->create()->id, 'status' => OfferStatus::Pending,
        ]);
    }

    public function test_acceptance_notifies_only_selected_provider_once_and_rolls_back_with_transaction(): void
    {
        $offer = $this->offer();
        $other = OrderOffer::factory()->create(['order_id' => $offer->order_id, 'store_id' => Store::factory()->create()->id]);
        $service = app(OrderService::class);
        DB::beginTransaction();
        $service->acceptOffer($offer->order, $offer);
        $this->assertSame('offer_accepted', $offer->store->owner->notifications()->sole()->data['type']);
        DB::rollBack();
        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame(OrderStatus::Pending, $offer->order->fresh()->status);
        $service->acceptOffer($offer->order->fresh(), $offer->fresh());
        $service->acceptOffer($offer->order->fresh(), $offer->fresh());
        $this->assertSame($offer->id, $offer->store->owner->notifications()->sole()->data['offer_id']);
        $this->assertSame(0, $other->store->owner->notifications()->count());
        $this->assertSame(0, $offer->order->customer->notifications()->count());
    }

    public function test_review_decisions_notify_applicant_without_private_reasons_and_no_duplicate_on_replay(): void
    {
        $admin = Admin::factory()->create();
        foreach (['accept' => 'store_approved', 'reject' => 'store_rejected'] as $action => $type) {
            $user = User::factory()->provider()->create();
            $request = StoreRequest::factory()->pending()->create(['user_id' => $user->id]);
            $this->actingAs($admin, 'sanctum')->postJson("/api/admin/store-requests/{$request->id}/{$action}", ['reason' => 'Confidential review detail'])
                ->assertOk();
            $this->postJson("/api/admin/store-requests/{$request->id}/{$action}", ['reason' => 'Confidential review detail'])->assertUnprocessable();
            $data = $user->notifications()->sole()->data;
            $this->assertSame($type, $data['type']);
            $this->assertSame($request->id, $data['request_id']);
            $this->assertStringNotContainsString('Confidential', json_encode($data));
        }
    }

    public function test_review_inbox_write_is_rolled_back_with_review(): void
    {
        $request = StoreRequest::factory()->pending()->create();
        DB::beginTransaction();
        app(AdminStoreRequestService::class)->reject($request, Admin::factory()->create(), 'Private review reason');
        $this->assertDatabaseCount('notifications', 1);
        DB::rollBack();
        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame('pending', $request->fresh()->request_status->value);
    }

    public function test_existing_paid_completed_and_customer_message_events_reach_provider(): void
    {
        $offer = $this->offer();
        $order = $offer->order;
        $order->update(['accepted_offer_id' => $offer->id]);
        OrderPaid::dispatch($order);
        OrderCompleted::dispatch($order);
        $conversation = Conversation::factory()->create(['provider_id' => $offer->store->user_id, 'customer_id' => $order->customer_id]);
        MessageSent::dispatch(Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $order->customer_id]));
        $this->assertEqualsCanonicalizing(['order_paid', 'order_completed', 'new_message'], $offer->store->owner->notifications()->get()->pluck('data.type')->all());
    }

    public function test_provider_push_is_queued_after_commit_and_worker_sends_only_allowlisted_identifiers(): void
    {
        config(['push.enabled' => true]);
        Queue::fake();
        Http::preventStrayRequests();
        $user = User::factory()->provider()->create();
        $session = $user->createToken('provider-app');
        $this->withToken($session->plainTextToken)->postJson('/api/push/device', ['token' => 'provider-device', 'platform' => 'android', 'locale' => 'ar'])->assertOk();
        $offer = $this->offer();
        $specific = Order::factory()->specific()->create(['customer_id' => User::factory()->customer()->create()->id]);
        $request = StoreRequest::factory()->accepted()->create(['user_id' => $user->id]);
        foreach ([new OfferAcceptedNotification($offer), new OrderPaidNotification($specific), new OrderCompletedNotification($specific), new NewOrderNotification($specific), new StoreRequestReviewedNotification($request)] as $notification) {
            $user->notify($notification);
        }
        Queue::assertPushed(SendCustomerPush::class, 5);
        Http::assertNothingSent();
        $this->mock(FcmAccessToken::class)->shouldReceive('get')->andReturn('test-oauth');
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'sent'])]);
        foreach (Queue::pushed(SendCustomerPush::class) as $job) {
            $this->assertTrue($job->afterCommit);
            $job->handle(app(DeviceTokenRepository::class), app(FcmSender::class));
        }
        Http::assertSentCount(5);
        foreach (Http::recorded() as [$sent]) {
            $data = $sent['message']['data'];
            $this->assertSame((string) $user->id, $data['recipient_id']);
            $this->assertEmpty(array_diff(array_keys($data), ['notification_id', 'recipient_id', 'type', 'offer_id', 'order_id', 'order_type', 'request_id']));
            $this->assertSame(trans('push.'.$data['type'], [], 'ar'), $sent['message']['notification']['body']);
            $this->assertStringNotContainsString(':id', $sent['message']['notification']['body']);
            $this->assertNotSame('push.'.$data['type'], $sent['message']['notification']['body']);
        }
        $user->notify(new NewOrderNotification(Order::factory()->general()->create(['customer_id' => User::factory()->customer()->create()->id])));
        Queue::assertPushed(SendCustomerPush::class, 5);
        $job = Queue::pushed(SendCustomerPush::class)->first();
        $user->notifications()->whereKey($job->notificationId)->update(['read_at' => now()]);
        $job->handle(app(DeviceTokenRepository::class), app(FcmSender::class));
        $this->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('device_tokens', 0);
        $job->handle(app(DeviceTokenRepository::class), app(FcmSender::class));
        Http::assertSentCount(5);
    }
}
