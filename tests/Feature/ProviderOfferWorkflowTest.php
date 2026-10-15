<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Events\OfferCreated;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProviderOfferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function setupOffer(): array
    {
        Event::fake([OfferCreated::class]);
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $order = Order::factory()->create(['customer_id' => User::factory()->customer()->create()->id]);
        $this->actingAs($provider, 'sanctum');

        return [$provider, $store, $order];
    }

    public function test_provider_receipts_and_list_include_request_identity_and_permissions(): void
    {
        [$provider, $store, $order] = $this->setupOffer();
        $receipt = $this->postJson("/api/provider/orders/{$order->id}/offer", [
            'store_id' => $store->id, 'price' => 12345, 'notes' => str_repeat('a', 1000),
        ])->assertCreated()->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.can_edit', true)->assertJsonPath('data.can_withdraw', true)
            ->assertJsonPath('data.price', 12345)->json('data');
        OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => Store::factory()->create()->id]);
        $this->getJson('/api/provider/orders/offers?order_id='.$order->id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/provider/orders/offers/'.$receipt['id'])->assertOk()->assertJsonPath('data.store.id', $store->id)
            ->assertJsonMissingPath('data.customer')->assertJsonMissingPath('data.order')->assertJsonMissingPath('data.accepted_store');
        $this->getJson('/api/provider/orders/offers?order_id=99999')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/provider/orders/offers?page=0')->assertUnprocessable();
    }

    public function test_own_historical_offer_remains_readable_without_exposing_winner(): void
    {
        [$provider, $store, $order] = $this->setupOffer();
        $mine = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $store->id, 'status' => OfferStatus::NotSelected]);
        $winner = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => Store::factory()->create()->id, 'status' => OfferStatus::Accepted]);
        $order->update(['status' => OrderStatus::AwaitingPayment, 'accepted_offer_id' => $winner->id]);
        $this->getJson('/api/provider/orders/offers/'.$mine->id)->assertOk()->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_withdraw', false)->assertJsonMissingPath('data.accepted_offer_id');
        $this->getJson('/api/provider/orders/offers/'.$winner->id)->assertNotFound();
        $this->getJson('/api/provider/orders/'.$order->id)->assertForbidden();
        $this->putJson("/api/provider/orders/{$order->id}/offer/{$mine->id}", ['price' => 900])->assertForbidden();
        $this->deleteJson("/api/provider/orders/{$order->id}/offer/{$mine->id}")->assertForbidden();
    }

    public function test_withdrawn_offer_can_be_resubmitted_without_unique_constraint_failure(): void
    {
        [$provider, $store, $order] = $this->setupOffer();
        $data = ['store_id' => $store->id, 'price' => 1000, 'notes' => 'Old'];
        $id = $this->postJson("/api/provider/orders/{$order->id}/offer", $data)->assertCreated()->json('data.id');
        $this->deleteJson("/api/provider/orders/{$order->id}/offer/{$id}")->assertOk();
        $this->getJson('/api/provider/orders/offers/'.$id)->assertNotFound();
        $this->postJson("/api/provider/orders/{$order->id}/offer", ['store_id' => $store->id, 'price' => 2000])
            ->assertCreated()->assertJsonPath('data.id', $id)->assertJsonPath('data.price', 2000)->assertJsonPath('data.notes', null);
        $this->assertSame(1, OrderOffer::withTrashed()->where('order_id', $order->id)->count());
    }

    public function test_photo_create_preserve_replace_and_clear_via_method_override(): void
    {
        Storage::fake('images_local');
        [$provider, $store, $order] = $this->setupOffer();
        $id = $this->postJson("/api/provider/orders/{$order->id}/offer", ['store_id' => $store->id, 'price' => 100,
            'images' => [UploadedFile::fake()->createWithContent('part.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg=='))]])->assertCreated()->assertJsonCount(1, 'data.images')->json('data.id');
        $url = "/api/provider/orders/{$order->id}/offer/{$id}";
        $this->postJson($url, ['_method' => 'PUT', 'price' => 200, 'notes' => 'New'])->assertOk()->assertJsonCount(1, 'data.images');
        $this->postJson($url, ['_method' => 'PUT', 'images' => [UploadedFile::fake()->createWithContent('part.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg=='))]])->assertOk()->assertJsonCount(1, 'data.images');
        $this->postJson($url, ['_method' => 'PUT', 'clear_images' => true])->assertOk()->assertJsonCount(0, 'data.images');
        $this->postJson($url, ['_method' => 'PUT', 'clear_images' => true, 'images' => [UploadedFile::fake()->createWithContent('part.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg=='))]])->assertUnprocessable();
    }

    public function test_invalid_store_price_and_wrong_order_are_rejected(): void
    {
        [$provider, $store, $order] = $this->setupOffer();
        $inactive = Store::factory()->inactive()->create(['user_id' => $provider->id]);
        foreach ([$inactive->id, Store::factory()->create()->id] as $storeId) {
            $this->postJson("/api/provider/orders/{$order->id}/offer", ['store_id' => $storeId, 'price' => 100])->assertUnprocessable();
        }
        foreach ([0, -1, 1.5, 2147483648] as $price) {
            $this->postJson("/api/provider/orders/{$order->id}/offer", ['store_id' => $store->id, 'price' => $price])->assertUnprocessable();
        }
        $offer = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $store->id]);
        $otherOrder = Order::factory()->create(['customer_id' => $order->customer_id]);
        $this->putJson("/api/provider/orders/{$otherOrder->id}/offer/{$offer->id}", ['price' => 100])->assertNotFound();
    }

    public function test_unavailable_parent_order_does_not_break_the_offer_list(): void
    {
        [$provider, $store, $order] = $this->setupOffer();
        $offer = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $store->id]);
        $order->delete();
        $this->getJson('/api/provider/orders/offers')->assertOk()
            ->assertJsonPath('data.0.order_status', 'unavailable')
            ->assertJsonPath('data.0.can_edit', false)
            ->assertJsonPath('data.0.can_withdraw', false);
    }
}
