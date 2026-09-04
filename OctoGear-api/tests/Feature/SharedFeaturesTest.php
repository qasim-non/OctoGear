<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Rating;
use App\Models\Store;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    // ─── Ratings ──────────────────────────────────────────────────────────────

    public function test_customer_can_list_their_submitted_ratings(): void
    {
        $customer = User::factory()->customer()->create();
        $store = Store::factory()->create();

        Rating::factory()->create(['customer_id' => $customer->id, 'store_id' => $store->id, 'order_id' => Order::factory()->create(['customer_id' => $customer->id])->id, 'rating' => 5]);
        Rating::factory()->create(['customer_id' => $customer->id, 'store_id' => $store->id, 'order_id' => Order::factory()->create(['customer_id' => $customer->id])->id, 'rating' => 3]);

        $otherCustomer = User::factory()->customer()->create();
        Rating::factory()->create(['customer_id' => $otherCustomer->id, 'store_id' => $store->id, 'order_id' => Order::factory()->create(['customer_id' => $otherCustomer->id])->id]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/ratings')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_provider_can_list_ratings_on_their_stores(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $customer = User::factory()->customer()->create();

        Rating::factory()->create(['customer_id' => $customer->id, 'store_id' => $store->id, 'order_id' => Order::factory()->create(['customer_id' => $customer->id])->id, 'rating' => 4]);

        $otherStore = Store::factory()->create();
        Rating::factory()->create(['customer_id' => $customer->id, 'store_id' => $otherStore->id, 'order_id' => Order::factory()->create(['customer_id' => $customer->id])->id]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/ratings')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_provider_does_not_see_ratings_on_other_providers_stores(): void
    {
        $provider = User::factory()->provider()->create();
        Store::factory()->create(['user_id' => $provider->id]);

        $otherProvider = User::factory()->provider()->create();
        $otherStore = Store::factory()->create(['user_id' => $otherProvider->id]);
        $customer = User::factory()->customer()->create();

        Rating::factory()->create(['customer_id' => $customer->id, 'store_id' => $otherStore->id, 'order_id' => Order::factory()->create(['customer_id' => $customer->id])->id]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/ratings')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_unauthenticated_user_cannot_list_ratings(): void
    {
        $this->getJson('/api/ratings')
            ->assertUnauthorized();
    }

    // ─── Conversations ────────────────────────────────────────────────────────

    public function test_customer_can_list_their_conversations(): void
    {
        $customer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();

        Conversation::factory()->create(['customer_id' => $customer->id, 'provider_id' => $provider->id]);

        $otherCustomer = User::factory()->customer()->create();
        Conversation::factory()->create(['customer_id' => $otherCustomer->id, 'provider_id' => $provider->id]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_provider_can_list_their_conversations(): void
    {
        $provider = User::factory()->provider()->create();
        $customer = User::factory()->customer()->create();

        Conversation::factory()->create(['customer_id' => $customer->id, 'provider_id' => $provider->id]);

        $otherProvider = User::factory()->provider()->create();
        Conversation::factory()->create(['customer_id' => $customer->id, 'provider_id' => $otherProvider->id]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_customer_can_create_conversation_with_provider(): void
    {
        $customer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/conversations', ['provider_id' => $provider->id])
            ->assertOk()
            ->assertJsonPath('data.other_user.id', $provider->id);
    }

    public function test_provider_can_create_conversation_with_customer(): void
    {
        $provider = User::factory()->provider()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/conversations', ['customer_id' => $customer->id])
            ->assertOk()
            ->assertJsonPath('data.other_user.id', $customer->id);
    }

    public function test_customer_cannot_create_conversation_without_provider_id(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/conversations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider_id');
    }

    public function test_provider_cannot_create_conversation_without_customer_id(): void
    {
        $provider = User::factory()->provider()->create();

        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/conversations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');
    }

    public function test_participant_can_view_conversation_messages(): void
    {
        $customer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();
        $conversation = Conversation::factory()->create(['customer_id' => $customer->id, 'provider_id' => $provider->id]);
        Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $customer->id]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_non_participant_cannot_view_conversation_messages(): void
    {
        $customer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();
        $conversation = Conversation::factory()->create(['customer_id' => $customer->id, 'provider_id' => $provider->id]);
        $outsider = User::factory()->customer()->create();

        $this->actingAs($outsider, 'sanctum')
            ->getJson("/api/conversations/{$conversation->id}/messages")
            ->assertForbidden();
    }

    public function test_participant_can_send_message(): void
    {
        $customer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();
        $conversation = Conversation::factory()->create(['customer_id' => $customer->id, 'provider_id' => $provider->id]);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/conversations/{$conversation->id}/messages", ['content' => 'Hello from provider'])
            ->assertCreated()
            ->assertJsonPath('data.content', 'Hello from provider')
            ->assertJsonPath('data.sender_id', $provider->id);
    }

    public function test_non_participant_cannot_send_message(): void
    {
        $customer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();
        $conversation = Conversation::factory()->create(['customer_id' => $customer->id, 'provider_id' => $provider->id]);
        $outsider = User::factory()->customer()->create();

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/conversations/{$conversation->id}/messages", ['content' => 'Spam'])
            ->assertForbidden();
    }

    public function test_existing_conversation_is_reused_not_duplicated(): void
    {
        $customer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/conversations', ['provider_id' => $provider->id])
            ->assertOk();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/conversations', ['provider_id' => $provider->id])
            ->assertOk();

        $this->assertDatabaseCount('conversations', 1);
    }

    // ─── Notifications ────────────────────────────────────────────────────────

    public function test_customer_can_list_their_notifications(): void
    {
        $customer = User::factory()->customer()->create();
        $customer->notify(new NewOrderNotification(
            Order::factory()->create(['customer_id' => $customer->id])
        ));

        $otherCustomer = User::factory()->customer()->create();
        $otherCustomer->notify(new NewOrderNotification(
            Order::factory()->create(['customer_id' => $otherCustomer->id])
        ));

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_provider_can_list_their_notifications(): void
    {
        $provider = User::factory()->provider()->create();
        $provider->notify(new NewOrderNotification(
            Order::factory()->create(['customer_id' => User::factory()->customer()->create()->id])
        ));

        $otherProvider = User::factory()->provider()->create();
        $otherProvider->notify(new NewOrderNotification(
            Order::factory()->create(['customer_id' => User::factory()->customer()->create()->id])
        ));

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $customer = User::factory()->customer()->create();
        $customer->notify(new NewOrderNotification(
            Order::factory()->create(['customer_id' => $customer->id])
        ));
        $notification = $customer->notifications()->first();

        $this->actingAs($customer, 'sanctum')
            ->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk();

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $customer = User::factory()->customer()->create();
        $customer->notify(new NewOrderNotification(
            Order::factory()->create(['customer_id' => $customer->id])
        ));
        $customer->notify(new NewOrderNotification(
            Order::factory()->create(['customer_id' => $customer->id])
        ));

        $this->actingAs($customer, 'sanctum')
            ->patchJson('/api/notifications/read-all')
            ->assertOk();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertEquals(0, $customer->unreadNotifications()->count());
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $customer = User::factory()->customer()->create();
        $customer->notify(new NewOrderNotification(
            Order::factory()->create(['customer_id' => $customer->id])
        ));
        $notification = $customer->notifications()->first();

        $outsider = User::factory()->customer()->create();

        $this->actingAs($outsider, 'sanctum')
            ->patchJson("/api/notifications/{$notification->id}/read")
            ->assertForbidden();
    }
}
