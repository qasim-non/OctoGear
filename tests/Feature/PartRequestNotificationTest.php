<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_event_listener_notifies_only_the_target_store_once_including_after_retry(): void
    {
        // Do not fake events/notifications: duplicate listener registration
        // must be caught at the API boundary, not hidden by an Event fake.
        $part = StoreCarComponent::factory()->create(['stock_quantity' => 3]);
        $otherStore = Store::factory()->create();
        $customer = User::factory()->customer()->create();
        $data = ['order_type' => 'specific', 'store_car_component_id' => $part->id, 'quantity' => 2];
        $headers = ['Idempotency-Key' => (string) Str::uuid()];

        $first = $this->actingAs($customer, 'sanctum')->postJson('/api/customer/orders', $data, $headers)
            ->assertCreated();
        $this->postJson('/api/customer/orders', $data, $headers)
            ->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));

        $notifications = $part->storeCar->store->owner->notifications()->get();
        $this->assertCount(1, $notifications);
        $this->assertSame(NewOrderNotification::class, $notifications->sole()->type);
        $this->assertSame($first->json('data.id'), $notifications->sole()->data['order_id']);
        $this->assertSame(0, $otherStore->owner->notifications()->count());
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertDatabaseCount('orders', 1);
    }
}
