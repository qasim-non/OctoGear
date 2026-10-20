<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\UserStatus;
use App\Http\Resources\ChatRealtimeConversationResource;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;
use App\Models\User;
use App\Repositories\ConversationRepository;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProviderOrderChatTest extends TestCase
{
    use RefreshDatabase;

    private function context(bool $general = true, OrderStatus $status = OrderStatus::AwaitingPayment): array
    {
        $provider = User::factory()->provider()->create();
        $customer = User::factory()->customer()->create(['full_name' => 'Saleh Customer']);
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $part = null;
        if (! $general) {
            $car = StoresCar::factory()->create(['store_id' => $store->id]);
            $part = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        }
        $order = Order::factory()->create([
            'customer_id' => $customer->id, 'status' => $status,
            'order_type' => $general ? OrderType::General : OrderType::Specific,
            'store_car_component_id' => $part?->id,
        ]);
        if ($general) {
            $offer = OrderOffer::factory()->create([
                'order_id' => $order->id, 'store_id' => $store->id, 'status' => OfferStatus::Accepted,
            ]);
            $order->update(['accepted_offer_id' => $offer->id]);
        }
        Sanctum::actingAs($provider);

        return [$provider, $customer, $store, $order, "/api/provider/orders/{$order->id}/conversation"];
    }

    private function payload(string $content = 'Hello customer'): array
    {
        return ['content' => $content, 'client_message_id' => (string) Str::uuid()];
    }

    public function test_general_order_open_is_read_only_and_provider_first_send_is_idempotent(): void
    {
        [$provider, $customer, $store, $order, $url] = $this->context();
        $this->getJson($url)->assertOk()->assertJsonPath('data.conversation', null)
            ->assertJsonPath('data.can_send', true)->assertJsonPath('data.other_user.name', 'Saleh Customer')
            ->assertJsonPath('data.offer_id', $order->accepted_offer_id)
            ->assertJsonMissingPath('data.other_user.mobile');
        $this->assertDatabaseCount('conversations', 0);
        $this->postJson("$url/messages", ['content' => 'Hello'])->assertUnprocessable();
        $this->postJson("$url/messages", $this->payload('  '))->assertUnprocessable();
        $this->assertDatabaseCount('conversations', 0);

        $payload = $this->payload();
        $id = $this->postJson("$url/messages", $payload)->assertOk()
            ->assertJsonPath('data.conversation.order_id', $order->id)
            ->assertJsonPath('data.conversation.other_user.name', 'Saleh Customer')
            ->assertJsonPath('data.message.is_mine', true)->json('data.conversation.id');
        $this->postJson("$url/messages", $payload)->assertOk()->assertJsonPath('data.conversation.id', $id);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('conversations', [
            'id' => $id, 'customer_id' => $customer->id, 'provider_id' => $provider->id,
            'offer_id' => $order->accepted_offer_id, 'order_id' => null,
        ]);
        $this->postJson("$url/messages", [...$payload, 'content' => 'Changed retry'])->assertConflict();
        Sanctum::actingAs($customer);
        $this->getJson("/api/customer/orders/{$order->id}/offers/{$order->accepted_offer_id}/conversation")
            ->assertOk()->assertJsonPath('data.conversation.id', $id);
        $this->getJson('/api/conversations?with_messages=true')->assertOk()
            ->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.unread_count', 1);
        $this->postJson("/api/conversations/$id/messages", $this->payload('Thanks'))->assertCreated();
    }

    public function test_provider_reuses_customer_offer_chat_even_for_retained_deleted_accepted_offer(): void
    {
        [$provider, $customer, , $order, $url] = $this->context();
        Sanctum::actingAs($customer);
        $offerUrl = "/api/customer/orders/{$order->id}/offers/{$order->accepted_offer_id}/conversation/messages";
        $id = $this->postJson($offerUrl, $this->payload())->assertOk()->json('data.conversation.id');
        $order->acceptedOffer->delete();
        Sanctum::actingAs($provider);
        $this->getJson($url)->assertOk()->assertJsonPath('data.conversation.id', $id);
        $this->postJson("$url/messages", $this->payload('Delivery details'))->assertOk()
            ->assertJsonPath('data.conversation.id', $id);
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_direct_orders_reuse_legacy_pair_chat_and_preserve_reconciliation(): void
    {
        [$provider, $customer, $store, $order, $url] = $this->context(false, OrderStatus::Paid);
        $legacy = Conversation::factory()->create(['customer_id' => $customer->id, 'provider_id' => $provider->id]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.conversation.id', $legacy->id)->assertJsonPath('data.offer_id', null);
        $payload = $this->payload();
        $id = $this->postJson("$url/messages", $payload)->assertOk()
            ->assertJsonPath('data.conversation.order_id', null)
            ->assertJsonPath('data.conversation.store', null)
            ->assertJsonPath('data.conversation.offer_id', null)->json('data.conversation.id');
        $this->assertSame($legacy->id, $id);
        $this->postJson("$url/messages", $payload)->assertOk()->assertJsonPath('data.conversation.id', $id);
        $this->assertDatabaseCount('messages', 1);
        $this->postJson('/api/conversations', ['customer_id' => $customer->id])->assertOk()
            ->assertJsonPath('data.id', $legacy->id)->assertJsonPath('data.order_id', null);

        $chat = app(ConversationRepository::class)->loadRealtimeSummary(Conversation::findOrFail($id));
        $snapshot = (new ChatRealtimeConversationResource(app(ChatService::class)->details($chat, $provider)))->resolve();
        $this->assertNull($snapshot['order_id']);
        $this->assertNull($snapshot['store']);
        Sanctum::actingAs($customer);
        $this->getJson('/api/conversations?with_messages=true')->assertOk()
            ->assertJsonPath('data.0.id', $id);
        $this->postJson("/api/conversations/$id/messages", $this->payload('Delivery address'))->assertCreated();

        // Completion removes dashboard initiation, while participant history remains usable.
        $order->update(['status' => OrderStatus::Completed]);
        Sanctum::actingAs($provider);
        $this->getJson($url)->assertOk()->assertJsonPath('data.conversation.id', $id);
        $this->postJson("$url/messages", $this->payload('New after completion'))->assertForbidden();
        $this->getJson("/api/conversations/$id")->assertOk()->assertJsonPath('data.can_send', true);
        $this->postJson("/api/conversations/$id/messages", $this->payload('Thank you'))->assertCreated();
        $order->storeCarComponent->delete();
        // The reused legacy pair chat has no dependency on this order's inventory.
        $this->getJson("/api/conversations/$id")->assertOk()->assertJsonPath('data.can_send', true);
        $this->postJson("/api/conversations/$id/messages", $this->payload('Still available'))->assertCreated();
    }

    public static function orderKinds(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('orderKinds')]
    public function test_lost_first_send_receipt_can_be_replayed_after_completion_without_creating_messages(bool $general): void
    {
        [$provider, , , $order, $url] = $this->context($general, OrderStatus::Paid);
        $payload = $this->payload('Delivery arranged');
        $first = $this->postJson("$url/messages", $payload)->assertOk();
        $conversationId = $first->json('data.conversation.id');
        $messageId = $first->json('data.message.id');
        $order->update(['status' => OrderStatus::Completed]);

        $this->getJson($url)->assertOk()->assertJsonPath('data.conversation.id', $conversationId);
        $this->postJson("$url/messages", $payload)->assertOk()
            ->assertJsonPath('data.conversation.id', $conversationId)
            ->assertJsonPath('data.message.id', $messageId);
        $this->postJson("$url/messages", [...$payload, 'content' => 'Changed retry'])->assertConflict();
        $this->postJson("$url/messages", $this->payload('New message'))->assertForbidden();
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('notifications', 1);

        Sanctum::actingAs(User::factory()->provider()->create());
        $this->getJson($url)->assertForbidden();
        $this->postJson("$url/messages", $payload)->assertForbidden();
        Sanctum::actingAs($provider);
        $this->assertDatabaseCount('conversations', 1);
    }

    public static function statuses(): array
    {
        $cases = [];
        foreach ([true, false] as $general) {
            foreach (OrderStatus::cases() as $status) {
                $cases[($general ? 'general ' : 'direct ').$status->value] = [$general, $status];
            }
        }

        return $cases;
    }

    #[DataProvider('statuses')]
    public function test_only_owned_awaiting_payment_and_paid_orders_allow_provider_initiation(bool $general, OrderStatus $status): void
    {
        [, , , , $url] = $this->context($general, $status);
        $allowed = in_array($status, [OrderStatus::AwaitingPayment, OrderStatus::Paid], true);
        $this->getJson($url)->assertStatus($allowed ? 200 : 403);
        $this->postJson("$url/messages", $this->payload())->assertStatus($allowed ? 200 : 403);
        $this->assertDatabaseCount('conversations', $allowed ? 1 : 0);
        $this->assertDatabaseCount('messages', $allowed ? 1 : 0);
    }

    public function test_other_provider_customer_and_nonselected_offer_owner_cannot_initiate(): void
    {
        [$provider, $customer, , $order, $url] = $this->context();
        $outsider = User::factory()->provider()->create();
        $otherStore = Store::factory()->create(['user_id' => $outsider->id]);
        OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $otherStore->id, 'status' => OfferStatus::NotSelected]);
        foreach ([$outsider, $customer] as $user) {
            Sanctum::actingAs($user);
            $this->getJson($url)->assertForbidden();
            $this->postJson("$url/messages", $this->payload())->assertForbidden();
        }
        Sanctum::actingAs($provider);
        $id = $this->postJson("$url/messages", $this->payload())->assertOk()->json('data.conversation.id');
        Sanctum::actingAs($outsider);
        $this->getJson("/api/conversations/$id")->assertForbidden();
        $this->postJson("/api/conversations/$id/messages", $this->payload())->assertForbidden();
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_blocked_customer_and_deleted_fulfillment_records_cannot_start_chat(): void
    {
        [$provider, $customer, $store, $order, $url] = $this->context();
        $customer->update(['status' => UserStatus::Blocked]);
        $this->getJson($url)->assertForbidden();
        $this->postJson("$url/messages", $this->payload())->assertForbidden();
        $customer->update(['status' => UserStatus::Unblocked]);
        $store->delete();
        $this->getJson($url)->assertForbidden();
        $this->postJson("$url/messages", $this->payload())->assertForbidden();
        $this->assertDatabaseCount('conversations', 0);
    }
}
