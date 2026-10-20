<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderChatOffersTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $provider = User::factory()->provider()->create();
        $customer = User::factory()->customer()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $offer = $this->offer($customer, $store);
        $chat = Conversation::factory()->create([
            'customer_id' => $customer->id, 'provider_id' => $provider->id,
            'offer_id' => $offer->id,
        ]);
        Sanctum::actingAs($provider);

        return [$provider, $customer, $store, $offer, $chat];
    }

    private function offer(User $customer, Store $store): OrderOffer
    {
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        return OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $store->id]);
    }

    private function url(Conversation $chat): string
    {
        return '/api/provider/conversations/'.$chat->id.'/offers?';
    }

    public function test_only_own_editable_offers_for_the_customer_are_returned_across_requests_and_stores(): void
    {
        [$provider, $customer, $store, $current, $chat] = $this->fixture();
        $secondStore = Store::factory()->create(['user_id' => $provider->id]);
        $anotherRequest = $this->offer($customer, $store);
        $sameRequest = OrderOffer::factory()->create(['order_id' => $current->order_id, 'store_id' => $secondStore->id]);
        $this->offer(User::factory()->customer()->create(), $store);
        $this->offer($customer, Store::factory()->create());
        $rejected = $this->offer($customer, $store);
        $rejected->update(['status' => OfferStatus::Rejected]);
        foreach ([OrderStatus::AwaitingPayment, OrderStatus::Paid, OrderStatus::Completed, OrderStatus::Cancelled] as $status) {
            $locked = $this->offer($customer, $store);
            $locked->order->update(['status' => $status]);
        }
        $removed = $this->offer($customer, $store);
        $removed->delete();
        $removedOrder = $this->offer($customer, $store);
        $removedOrder->order->delete();
        $response = $this->getJson($this->url($chat))->assertOk()
            ->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.id', $current->id)
            ->assertJsonMissingPath('data.0.customer')->assertJsonMissingPath('data.0.order');
        $this->assertEqualsCanonicalizing([$current->id, $anotherRequest->id, $sameRequest->id], array_column($response->json('data'), 'id'));
        foreach ($response->json('data') as $item) {
            $this->assertTrue($item['can_edit']);
        }
        $this->getJson($this->url($chat).'&order_id='.$current->order_id)
            ->assertOk()->assertJsonCount(2, 'data');
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_participant_provider_authorization_and_query_validation(): void
    {
        [$provider, $customer, , , $chat] = $this->fixture();
        foreach ([$customer, User::factory()->provider()->create()] as $outsider) {
            Sanctum::actingAs($outsider);
            $this->getJson($this->url($chat))->assertForbidden();
        }
        Sanctum::actingAs($provider);
        foreach (['0', '-1', 'abc', '1.5'] as $id) {
            $this->getJson('/api/provider/conversations/'.$id.'/offers')->assertNotFound();
        }
        $this->getJson('/api/provider/conversations/999999/offers')->assertNotFound();
        $this->getJson($this->url($chat).'&page=0')->assertUnprocessable();
        $provider->update(['status' => UserStatus::Blocked]);
        $this->getJson($this->url($chat))->assertForbidden();
    }

    public function test_legacy_and_direct_chats_resolve_customer_without_creating_messages(): void
    {
        [$provider, $customer, , $offer, $chat] = $this->fixture();
        $directOrder = Order::factory()->specific()->create(['customer_id' => $customer->id]);
        foreach ([null, $directOrder->id] as $orderId) {
            $chat->update(['offer_id' => null, 'order_id' => $orderId]);
            $this->getJson($this->url($chat))->assertOk()->assertJsonPath('data.0.id', $offer->id);
        }
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_current_offer_is_first_and_pagination_is_stable(): void
    {
        [, $customer, $store, $offer, $chat] = $this->fixture();
        for ($i = 0; $i < 16; $i++) {
            $this->offer($customer, $store);
        }
        $first = $this->getJson($this->url($chat))->assertOk()
            ->assertJsonPath('meta.total', 17)->assertJsonPath('data.0.id', $offer->id)
            ->assertJsonCount(15, 'data')->json('data');
        $second = $this->getJson($this->url($chat).'&page=2')->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertCount(17, array_unique(array_column([...$first, ...$second], 'id')));
    }

    public function test_selection_after_lookup_still_blocks_edit_and_refresh_removes_offer(): void
    {
        [, , , $offer, $chat] = $this->fixture();
        $this->getJson($this->url($chat))->assertOk()->assertJsonCount(1, 'data');
        $offer->order->update(['status' => OrderStatus::AwaitingPayment, 'accepted_offer_id' => $offer->id]);
        $offer->update(['status' => OfferStatus::Accepted]);
        $this->putJson("/api/provider/orders/{$offer->order_id}/offer/{$offer->id}", ['price' => 1])->assertForbidden();
        $this->getJson($this->url($chat))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/provider/orders/offers')->assertOk()->assertJsonCount(1, 'data');
    }
}
