<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\OfferCreated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    private function notification(User $user, array $attributes = []): DatabaseNotification
    {
        return $user->notifications()->create(array_merge([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\NewOfferNotification',
            'data' => ['type' => 'new_offer', 'order_id' => 17, 'offer_id' => 42],
            'created_at' => now()->subDay(),
        ], $attributes));
    }

    public function test_inbox_and_count_require_authentication(): void
    {
        $this->getJson('/api/notifications/inbox')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
    }

    public function test_offer_and_provider_message_events_reach_the_customer_inbox(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $offer = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => Store::factory()->create()->id]);
        OfferCreated::dispatch($offer);
        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id,
            'provider_id' => $offer->store->user_id,
        ]);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $conversation->provider_id,
        ]);
        MessageSent::dispatch($message);
        $response = $this->actingAs($customer, 'sanctum')->getJson('/api/notifications/inbox')
            ->assertOk()->assertJsonCount(2, 'data.items')->assertJsonPath('data.unread_count', 2);
        $items = collect($response->json('data.items'))->keyBy('payload.type');
        $this->assertSame($offer->id, $items['new_offer']['payload']['offer_id']);
        $this->assertSame($offer->order_id, $items['new_offer']['payload']['order_id']);
        $this->assertSame($conversation->id, $items['new_message']['payload']['conversation_id']);
        $this->assertFalse($items['new_message']['is_read']);
    }

    public function test_inbox_is_scoped_read_only_and_cursor_pages_survive_new_arrivals(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $expected = [];
        for ($i = 0; $i < 25; $i++) {
            $expected[] = $this->notification($customer)->id;
        }
        $this->notification($other);
        $this->actingAs($customer, 'sanctum');
        $first = $this->getJson('/api/notifications/inbox')->assertOk()
            ->assertJsonCount(20, 'data.items')->assertJsonPath('data.unread_count', 25);
        $new = $this->notification($customer, ['created_at' => now()]);
        $next = $this->getJson('/api/notifications/inbox?'.http_build_query(['cursor' => $first->json('data.next_cursor')]))
            ->assertOk()->assertJsonCount(5, 'data.items')->assertJsonPath('data.next_cursor', null);
        $actual = array_merge(array_column($first->json('data.items'), 'id'), array_column($next->json('data.items'), 'id'));
        $this->assertEqualsCanonicalizing($expected, $actual);
        $this->assertNotContains($new->id, $actual);
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 26);
        $this->assertSame(26, $customer->unreadNotifications()->count());
    }

    public function test_unread_cursor_survives_marking_the_first_page_read(): void
    {
        $customer = User::factory()->customer()->create();
        for ($i = 0; $i < 23; $i++) {
            $this->notification($customer);
        }
        $this->notification($customer, ['read_at' => now()]);
        $this->actingAs($customer, 'sanctum');
        $first = $this->getJson('/api/notifications/inbox?unread=1')->assertOk()->assertJsonCount(20, 'data.items');
        foreach ($first->json('data.items') as $item) {
            $this->patchJson('/api/notifications/'.$item['id'].'/read')->assertOk()->assertJsonPath('data.is_read', true);
        }
        $this->getJson('/api/notifications/inbox?'.http_build_query(['unread' => 1, 'cursor' => $first->json('data.next_cursor')]))
            ->assertOk()->assertJsonCount(3, 'data.items')->assertJsonPath('data.unread_count', 3);
    }

    public function test_read_actions_are_idempotent_and_cannot_change_other_accounts(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $own = $this->notification($customer);
        $foreign = $this->notification($other);
        $wrongType = $this->notification($customer);
        $wrongType->update(['notifiable_type' => 'unrelated_model']);
        $this->actingAs($customer, 'sanctum');
        $this->patchJson('/api/notifications/'.$foreign->id.'/read')->assertForbidden();
        $this->patchJson('/api/notifications/'.$wrongType->id.'/read')->assertForbidden();
        for ($i = 0; $i < 2; $i++) {
            $this->patchJson('/api/notifications/'.$own->id.'/read')->assertOk()->assertJsonPath('data.is_read', true);
            $this->patchJson('/api/notifications/read-all')->assertOk();
        }
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
        $this->assertNull($foreign->fresh()->read_at);
        $this->assertNull($wrongType->fresh()->read_at);
    }

    public function test_invalid_filters_and_cursors_return_validation_errors(): void
    {
        $this->actingAs(User::factory()->customer()->create(), 'sanctum');
        foreach (['broken', base64_encode('{}'), base64_encode(json_encode(['_pointsToNextItems' => true])), str_repeat('a', 2049)] as $cursor) {
            $this->getJson('/api/notifications/inbox?'.http_build_query(['cursor' => $cursor]))
                ->assertUnprocessable()->assertJsonValidationErrors('cursor');
        }
        $this->getJson('/api/notifications/inbox?unread=invalid')->assertUnprocessable()->assertJsonValidationErrors('unread');
    }
}
