<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_and_offer_entry_points_share_one_pair_across_stores_and_requests(): void
    {
        $customer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();
        Sanctum::actingAs($provider);
        $id = $this->postJson('/api/conversations', ['customer_id' => $customer->id])
            ->assertOk()->json('data.id');
        Sanctum::actingAs($customer);
        $this->postJson('/api/conversations', ['provider_id' => $provider->id])
            ->assertOk()->assertJsonPath('data.id', $id);
        foreach (range(1, 2) as $index) {
            $store = Store::factory()->create(['user_id' => $provider->id]);
            $order = Order::factory()->create(['customer_id' => $customer->id]);
            $offer = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $store->id]);
            $url = "/api/customer/orders/$order->id/offers/$offer->id/conversation";
            $this->getJson($url)->assertOk()->assertJsonPath('data.conversation.id', $id);
            $this->postJson("$url/messages", [
                'content' => "Request $index", 'client_message_id' => (string) Str::uuid(),
            ])->assertOk()->assertJsonPath('data.conversation.id', $id);
        }
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
        $this->getJson('/api/conversations')->assertOk()->assertJsonCount(1, 'data');
        Sanctum::actingAs($provider);
        $this->getJson('/api/conversations')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.unread_count', 2);
        $other = User::factory()->customer()->create();
        $otherId = $this->postJson('/api/conversations', ['customer_id' => $other->id])
            ->assertOk()->json('data.id');
        $this->assertNotSame($id, $otherId);
        Sanctum::actingAs($other);
        $this->getJson("/api/conversations/$id")->assertForbidden();
    }

    public function test_provider_direct_orders_and_customer_offer_send_share_the_first_chat(): void
    {
        $customer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();
        $id = null;
        foreach (range(1, 2) as $index) {
            $store = Store::factory()->create(['user_id' => $provider->id]);
            $car = StoresCar::factory()->create(['store_id' => $store->id]);
            $part = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
            $order = Order::factory()->specific()->create([
                'customer_id' => $customer->id, 'store_car_component_id' => $part->id,
                'status' => OrderStatus::Paid,
            ]);
            Sanctum::actingAs($provider);
            $response = $this->postJson("/api/provider/orders/$order->id/conversation/messages", [
                'content' => "Delivery $index", 'client_message_id' => (string) Str::uuid(),
            ])->assertOk();
            $id ??= $response->json('data.conversation.id');
            $response->assertJsonPath('data.conversation.id', $id);
            $general = Order::factory()->create(['customer_id' => $customer->id]);
            $offer = OrderOffer::factory()->create(['order_id' => $general->id, 'store_id' => $store->id]);
            Sanctum::actingAs($customer);
            $this->postJson("/api/customer/orders/$general->id/offers/$offer->id/conversation/messages", [
                'content' => "Offer $index", 'client_message_id' => (string) Str::uuid(),
            ])->assertOk()->assertJsonPath('data.conversation.id', $id);
        }
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 4);
        $this->assertDatabaseCount('notifications', 4);
    }

    public function test_soft_deleted_pair_is_reused_and_history_is_preserved(): void
    {
        $chat = Conversation::factory()->create([
            'customer_id' => User::factory()->customer()->create()->id,
            'provider_id' => User::factory()->provider()->create()->id,
        ]);
        $message = Message::factory()->create(['conversation_id' => $chat->id, 'sender_id' => $chat->customer_id]);
        $chat->delete();
        Sanctum::actingAs($chat->customer);
        $this->postJson('/api/conversations', ['provider_id' => $chat->provider_id])
            ->assertOk()->assertJsonPath('data.id', $chat->id);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('messages', ['id' => $message->id, 'conversation_id' => $chat->id]);
        $this->assertNull($chat->fresh()->deleted_at);
    }

    public function test_database_rejects_duplicates_even_if_the_api_is_bypassed(): void
    {
        $chat = Conversation::factory()->create([
            'customer_id' => User::factory()->customer()->create()->id,
            'provider_id' => User::factory()->provider()->create()->id,
        ]);
        $chat->delete();
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('conversations')->insert([
            'customer_id' => $chat->customer_id, 'provider_id' => $chat->provider_id,
        ]);
    }

    public function test_migration_refuses_existing_duplicates_without_modifying_history(): void
    {
        $migration = require database_path('migrations/2026_10_20_000001_unify_conversation_participants.php');
        $migration->down();
        $first = Conversation::factory()->create([
            'customer_id' => User::factory()->customer()->create()->id,
            'provider_id' => User::factory()->provider()->create()->id,
        ]);
        $second = Conversation::factory()->create([
            'customer_id' => $first->customer_id, 'provider_id' => $first->provider_id,
        ]);
        $message = Message::factory()->create([
            'conversation_id' => $second->id, 'sender_id' => $first->customer_id,
        ]);
        try {
            $migration->up();
            $this->fail('Existing duplicates must require explicit consolidation.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('No conversations or messages were changed', $error->getMessage());
        }
        $this->assertDatabaseCount('conversations', 2);
        $this->assertDatabaseHas('messages', ['id' => $message->id, 'conversation_id' => $second->id]);
        // The service still prevents another duplicate before schema rollout.
        Sanctum::actingAs($first->customer);
        $this->postJson('/api/conversations', ['provider_id' => $first->provider_id])->assertOk();
        $this->assertDatabaseCount('conversations', 2);
    }
}
