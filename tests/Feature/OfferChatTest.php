<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\StoreStatus;
use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfferChatTest extends TestCase
{
    use RefreshDatabase;

    private function setupOffer(): array
    {
        $customer = User::factory()->create();
        $store = Store::factory()->create(['employee_name' => 'Ahmed']);
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $offer = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $store->id, 'status' => OfferStatus::Pending]);
        Sanctum::actingAs($customer);

        return [$customer, $store, $order, $offer, "/api/customer/orders/{$order->id}/offers/{$offer->id}/conversation"];
    }

    public function test_open_is_read_only_and_first_send_is_atomic_and_reused(): void
    {
        [$customer, $store, $order, $offer, $url] = $this->setupOffer();
        $this->getJson($url)->assertOk()->assertJsonPath('data.conversation', null)
            ->assertJsonPath('data.store.employee_name', 'Ahmed')->assertJsonMissingPath('data.store.mobile');
        $this->assertDatabaseCount('conversations', 0);
        $this->postJson("$url/messages", ['content' => '  ', 'client_message_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('conversations', 0);
        $payload = ['content' => 'Hello', 'client_message_id' => (string) Str::uuid()];
        $response = $this->postJson("$url/messages", $payload)->assertOk()->assertJsonPath('data.message.content', 'Hello');
        $id = $response->json('data.conversation.id');
        $this->postJson("$url/messages", $payload)->assertOk()->assertJsonPath('data.conversation.id', $id);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->getJson($url)->assertOk()->assertJsonPath('data.conversation.id', $id);
        $this->postJson("/api/conversations/$id/messages", $payload)->assertCreated();
        $this->assertDatabaseCount('messages', 1);
        $this->postJson("$url/messages", array_merge($payload, ['content' => 'Different']))->assertConflict();
        $this->postJson("/api/conversations/$id/messages", ['content' => str_repeat('ش', 2000), 'client_message_id' => (string) Str::uuid()])->assertCreated();
        $this->postJson("/api/conversations/$id/messages", ['content' => str_repeat('a', 2001)])->assertUnprocessable();
    }

    public function test_provider_can_reply_and_only_participants_can_access(): void
    {
        [$customer, $store, $order, $offer, $url] = $this->setupOffer();
        $id = $this->postJson("$url/messages", ['content' => 'Hello', 'client_message_id' => (string) Str::uuid()])->json('data.conversation.id');
        Sanctum::actingAs(User::findOrFail($store->user_id));
        $this->getJson('/api/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 1);
        $this->postJson("/api/conversations/$id/messages", ['content' => 'Available', 'client_message_id' => (string) Str::uuid()])->assertCreated()->assertJsonPath('data.is_mine', true);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
        $this->getJson("/api/conversations/$id")->assertForbidden();
        $this->getJson("/api/conversations/$id/timeline")->assertForbidden();
        $this->postJson("/api/conversations/$id/messages", ['content' => 'No'])->assertForbidden();
        $this->patchJson("/api/conversations/$id/read", ['through_id' => 1])->assertForbidden();
    }

    public static function offerStatuses(): array
    {
        return array_map(fn (OfferStatus $status) => [$status], OfferStatus::cases());
    }

    #[DataProvider('offerStatuses')]
    public function test_customer_can_message_each_offer_store_even_when_inactive(OfferStatus $status): void
    {
        [$customer, $store, $order, $offer, $url] = $this->setupOffer();
        $store->update(['status' => StoreStatus::Inactive]);
        $offer->update(['status' => $status]);
        $otherStore = Store::factory()->create(['status' => StoreStatus::Inactive]);
        $otherOffer = OrderOffer::factory()->create([
            'order_id' => $order->id, 'store_id' => $otherStore->id, 'status' => OfferStatus::NotSelected,
        ]);

        $this->getJson($url)->assertOk()->assertJsonPath('data.can_send', true)
            ->assertJsonPath('data.conversation', null);
        $first = $this->postJson("$url/messages", [
            'content' => 'Is this part available?', 'client_message_id' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('data.conversation.can_send', true);
        $id = $first->json('data.conversation.id');

        $otherId = $this->postJson("/api/customer/orders/{$order->id}/offers/{$otherOffer->id}/conversation/messages", [
            'content' => 'Can you clarify your offer?', 'client_message_id' => (string) Str::uuid(),
        ])->assertOk()->json('data.conversation.id');
        $this->assertNotSame($id, $otherId);
        $this->assertDatabaseHas('conversations', ['id' => $id, 'customer_id' => $customer->id, 'provider_id' => $store->user_id]);
        $this->assertDatabaseHas('conversations', ['id' => $otherId, 'customer_id' => $customer->id, 'provider_id' => $otherStore->user_id]);

        Sanctum::actingAs($store->owner);
        $this->postJson("/api/conversations/$id/messages", [
            'content' => 'Yes, it is available.', 'client_message_id' => (string) Str::uuid(),
        ])->assertCreated();
        $this->postJson("/api/conversations/$otherId/messages", ['content' => 'Wrong store'])
            ->assertForbidden();

        Sanctum::actingAs($customer);
        $this->getJson($url)->assertOk()->assertJsonPath('data.conversation.can_send', true);
        $this->getJson("/api/conversations/$id")->assertOk()->assertJsonPath('data.can_send', true);
        $this->getJson('/api/conversations?with_messages=true')->assertOk()->assertJsonPath('data.0.can_send', true);

        $store->owner->update(['status' => UserStatus::Blocked]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.can_send', false);
        $this->postJson("/api/conversations/$id/messages", ['content' => 'Blocked'])->assertForbidden();
    }

    public function test_cursor_pagination_and_read_boundary(): void
    {
        [$customer, $store, $order, $offer, $url] = $this->setupOffer();
        $id = $this->postJson("$url/messages", ['content' => 'Hello', 'client_message_id' => (string) Str::uuid()])->json('data.conversation.id');
        for ($i = 0; $i < 45; $i++) {
            Message::create(['conversation_id' => $id, 'sender_id' => $store->user_id, 'content' => "Message $i", 'is_read' => false]);
        }
        $page = $this->getJson("/api/conversations/$id/timeline")->assertOk()->assertJsonCount(40, 'data.messages')->assertJsonPath('data.has_more', true);
        $oldest = collect($page->json('data.messages'))->min('id');
        $this->getJson("/api/conversations/$id/timeline?before_id=$oldest")->assertOk()->assertJsonCount(6, 'data.messages')->assertJsonPath('data.has_more', false);
        $this->getJson("/api/conversations/$id/timeline?after_id=1")->assertOk()->assertJsonCount(40, 'data.messages')->assertJsonPath('data.messages.0.id', 41);
        $this->patchJson("/api/conversations/$id/read", ['through_id' => 20])->assertOk();
        $this->assertDatabaseHas('messages', ['id' => 20, 'is_read' => true]);
        $this->assertDatabaseHas('messages', ['id' => 21, 'is_read' => false]);
        $this->assertDatabaseHas('messages', ['id' => 1, 'is_read' => false]);
        $this->getJson('/api/conversations')->assertJsonPath('data.0.unread_count', 26);
    }

    public function test_existing_offer_chat_remains_writable_when_store_becomes_inactive(): void
    {
        [, $store, , , $url] = $this->setupOffer();
        $id = $this->postJson("$url/messages", [
            'content' => 'Hello', 'client_message_id' => (string) Str::uuid(),
        ])->assertOk()->json('data.conversation.id');

        $store->update(['status' => StoreStatus::Inactive]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.can_send', true);
        $this->postJson("/api/conversations/$id/messages", ['content' => 'Following up'])->assertCreated();

        $store->delete();
        $this->getJson("/api/conversations/$id")->assertOk()->assertJsonPath('data.can_send', false);
        $this->postJson("/api/conversations/$id/messages", ['content' => 'Deleted store'])->assertForbidden();
        $this->getJson("/api/conversations/$id/timeline")->assertOk()->assertJsonCount(2, 'data.messages');
    }

    public function test_failed_first_send_rolls_back_and_offer_nesting_is_checked(): void
    {
        [$customer, $store, $order, $offer, $url] = $this->setupOffer();
        User::findOrFail($store->user_id)->update(['status' => UserStatus::Blocked]);
        $this->postJson("$url/messages", ['content' => 'Hello', 'client_message_id' => (string) Str::uuid()])->assertForbidden();
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
        $other = Order::factory()->create(['customer_id' => $customer->id]);
        $this->getJson("/api/customer/orders/{$other->id}/offers/{$offer->id}/conversation")->assertNotFound();
    }

    public function test_inbox_uses_message_activity_and_hides_empty_legacy_chats(): void
    {
        [$customer, $store, $order, $offer, $url] = $this->setupOffer();
        Conversation::create(['customer_id' => $customer->id, 'provider_id' => $store->user_id]);
        $this->getJson('/api/conversations?with_messages=1')->assertJsonCount(0, 'data');
        $this->postJson("$url/messages", ['content' => 'Hello', 'client_message_id' => (string) Str::uuid()])->assertOk();
        $this->getJson('/api/conversations?with_messages=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.latest_message.content', 'Hello');
    }

    public function test_offer_threads_are_separate_and_cross_chat_retry_cannot_create_empty_chat(): void
    {
        [$customer, $store, $order, $offer, $url] = $this->setupOffer();
        $payload = ['content' => 'Hello', 'client_message_id' => (string) Str::uuid()];
        $first = $this->postJson("$url/messages", $payload)->assertOk()->json('data.conversation.id');
        $order2 = Order::factory()->create(['customer_id' => $customer->id]);
        $offer2 = OrderOffer::factory()->create(['order_id' => $order2->id, 'store_id' => $store->id, 'status' => OfferStatus::Pending]);
        $url2 = "/api/customer/orders/{$order2->id}/offers/{$offer2->id}/conversation";
        $this->postJson("$url2/messages", $payload)->assertConflict();
        $this->assertDatabaseCount('conversations', 1);
        $this->postJson("$url2/messages", ['content' => 'Another request', 'client_message_id' => (string) Str::uuid()])->assertOk();
        $this->assertDatabaseCount('conversations', 2);
        $this->postJson("/api/conversations/$first/messages", ['content' => 'Newest message', 'client_message_id' => (string) Str::uuid()])->assertCreated();
        $this->getJson('/api/conversations')->assertJsonPath('data.0.id', $first)->assertJsonPath('data.0.latest_message.content', 'Newest message');
    }
}
