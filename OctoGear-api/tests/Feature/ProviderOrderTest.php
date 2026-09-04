<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_can_list_general_pending_orders(): void
    {
        $provider = User::factory()->provider()->create();
        $customer = User::factory()->customer()->create();

        $generalOrder = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/orders/general')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $generalOrder->id);
    }

    public function test_provider_can_filter_general_orders_by_city(): void
    {
        $provider = User::factory()->provider()->create();
        $cityA = \App\Models\City::factory()->create();
        $cityB = \App\Models\City::factory()->create();
        $customerA = User::factory()->customer()->create(['city_id' => $cityA->id]);
        $customerB = User::factory()->customer()->create(['city_id' => $cityB->id]);

        $orderA = Order::factory()->create([
            'customer_id' => $customerA->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);
        Order::factory()->create([
            'customer_id' => $customerB->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/orders/general?city_id={$cityA->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $orderA->id);
    }

    public function test_provider_defaults_general_orders_to_their_store_city(): void
    {
        $provider = User::factory()->provider()->create();
        $cityA = \App\Models\City::factory()->create();
        $cityB = \App\Models\City::factory()->create();
        Store::factory()->create(['user_id' => $provider->id, 'city_id' => $cityA->id]);
        $customerA = User::factory()->customer()->create(['city_id' => $cityA->id]);
        $customerB = User::factory()->customer()->create(['city_id' => $cityB->id]);

        $orderA = Order::factory()->create([
            'customer_id' => $customerA->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);
        Order::factory()->create([
            'customer_id' => $customerB->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/orders/general')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $orderA->id);
    }

    public function test_provider_general_orders_validate_city_id(): void
    {
        $provider = User::factory()->provider()->create();

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/orders/general?city_id=999999')
            ->assertStatus(422);
    }

    public function test_provider_does_not_see_completed_general_orders(): void
    {
        $provider = User::factory()->provider()->create();
        $customer = User::factory()->customer()->create();

        Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Completed,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/orders/general')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_provider_sees_specific_orders_targeting_their_store(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        $component = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id'             => $customer->id,
            'order_type'              => OrderType::Specific,
            'status'                  => OrderStatus::Pending,
            'store_car_component_id'  => $component->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/orders/specific')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $order->id);
    }

    public function test_provider_does_not_see_specific_orders_for_other_stores(): void
    {
        $provider = User::factory()->provider()->create();
        $otherStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $car = StoresCar::factory()->create(['store_id' => $otherStore->id]);
        $component = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        $customer = User::factory()->customer()->create();

        Order::factory()->create([
            'customer_id'             => $customer->id,
            'order_type'              => OrderType::Specific,
            'status'                  => OrderStatus::Pending,
            'store_car_component_id'  => $component->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/orders/specific')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_provider_can_list_their_own_offers(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Negotiating,
        ]);

        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/orders/offers')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $offer->id);
    }

    public function test_provider_can_view_a_general_order(): void
    {
        $provider = User::factory()->provider()->create();
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_provider_cannot_view_non_pending_general_order_they_did_not_bid_on(): void
    {
        $provider = User::factory()->provider()->create();
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Completed,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/orders/{$order->id}")
            ->assertStatus(403);
    }

    public function test_provider_who_lost_cannot_view_accepted_general_order(): void
    {
        $provider = User::factory()->provider()->create();
        $myStore = Store::factory()->create(['user_id' => $provider->id]);
        $winningStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id'       => $customer->id,
            'order_type'        => OrderType::General,
            'status'            => OrderStatus::Negotiating,
            'accepted_store_id' => $winningStore->id,
        ]);

        OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $myStore->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/orders/{$order->id}")
            ->assertStatus(403);
    }

    public function test_provider_who_won_can_view_accepted_general_order(): void
    {
        $provider = User::factory()->provider()->create();
        $winningStore = Store::factory()->create(['user_id' => $provider->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id'       => $customer->id,
            'order_type'        => OrderType::General,
            'status'            => OrderStatus::Negotiating,
            'accepted_store_id' => $winningStore->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/orders/{$order->id}")
            ->assertOk();
    }

    public function test_provider_does_not_see_other_providers_offers_on_order(): void
    {
        $provider = User::factory()->provider()->create();
        $myStore = Store::factory()->create(['user_id' => $provider->id]);
        $otherStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);

        OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $otherStore->id,
            'price'    => 250,
        ]);

        $myOffer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $myStore->id,
            'price'    => 500,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/orders/{$order->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.offers')
            ->assertJsonPath('data.offers.0.id', $myOffer->id)
            ->assertJsonPath('data.offers.0.price', 500);
    }

    public function test_provider_can_view_specific_order_for_their_store(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        $component = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id'             => $customer->id,
            'order_type'              => OrderType::Specific,
            'status'                  => OrderStatus::Pending,
            'store_car_component_id'  => $component->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_provider_cannot_view_specific_order_for_other_store(): void
    {
        $provider = User::factory()->provider()->create();
        $otherStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $car = StoresCar::factory()->create(['store_id' => $otherStore->id]);
        $component = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id'             => $customer->id,
            'order_type'              => OrderType::Specific,
            'status'                  => OrderStatus::Pending,
            'store_car_component_id'  => $component->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/orders/{$order->id}")
            ->assertStatus(403);
    }

    public function test_specific_order_has_no_offers_in_provider_response(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        $component = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id'             => $customer->id,
            'order_type'              => OrderType::Specific,
            'status'                  => OrderStatus::Pending,
            'store_car_component_id'  => $component->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/orders/{$order->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.offers');
    }

    public function test_provider_can_create_offer_on_general_order(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/orders/{$order->id}/offer", [
                'store_id' => $store->id,
                'price'    => 500,
                'notes'    => 'Good quality part',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.price', 500)
            ->assertJsonPath('data.store.id', $store->id);
    }

    public function test_provider_can_create_offer_with_any_of_their_stores(): void
    {
        $provider = User::factory()->provider()->create();
        $storeA = Store::factory()->create(['user_id' => $provider->id]);
        $storeB = Store::factory()->create(['user_id' => $provider->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/orders/{$order->id}/offer", [
                'store_id' => $storeB->id,
                'price'    => 700,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.store.id', $storeB->id);

        $this->assertDatabaseHas('order_offers', [
            'order_id' => $order->id,
            'store_id' => $storeB->id,
        ]);
    }

    public function test_provider_cannot_offer_with_store_that_is_not_theirs(): void
    {
        $provider = User::factory()->provider()->create();
        $otherProviderStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/orders/{$order->id}/offer", [
                'store_id' => $otherProviderStore->id,
                'price'    => 500,
            ])
            ->assertStatus(422);
    }

    public function test_provider_cannot_offer_on_specific_order(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        $component = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id'             => $customer->id,
            'order_type'              => OrderType::Specific,
            'status'                  => OrderStatus::Pending,
            'store_car_component_id'  => $component->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/orders/{$order->id}/offer", [
                'store_id' => $store->id,
                'price'    => 500,
            ])
            ->assertStatus(403);
    }

    public function test_provider_cannot_offer_twice_on_same_order(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);

        OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/orders/{$order->id}/offer", [
                'store_id' => $store->id,
                'price'    => 500,
            ])
            ->assertStatus(400);
    }

    public function test_provider_can_update_their_own_offer(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);

        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'price'    => 400,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->putJson("/api/provider/orders/{$order->id}/offer/{$offer->id}", ['price' => 600])
            ->assertOk()
            ->assertJsonPath('data.price', 600);
    }

    public function test_provider_cannot_update_another_stores_offer(): void
    {
        $provider = User::factory()->provider()->create();
        Store::factory()->create(['user_id' => $provider->id]);
        $otherStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);

        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $otherStore->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->putJson("/api/provider/orders/{$order->id}/offer/{$offer->id}", ['price' => 1000])
            ->assertStatus(403);
    }

    public function test_provider_can_delete_their_own_offer(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type'  => OrderType::General,
            'status'      => OrderStatus::Pending,
        ]);

        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->deleteJson("/api/provider/orders/{$order->id}/offer/{$offer->id}")
            ->assertOk();

        $this->assertSoftDeleted('order_offers', ['id' => $offer->id]);
    }

    public function test_provider_can_reject_specific_order_for_their_store(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        $component = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id'             => $customer->id,
            'order_type'              => OrderType::Specific,
            'status'                  => OrderStatus::Pending,
            'store_car_component_id'  => $component->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/orders/{$order->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Rejected->value);
    }

    public function test_provider_cannot_reject_specific_order_for_other_store(): void
    {
        $provider = User::factory()->provider()->create();
        $otherStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $car = StoresCar::factory()->create(['store_id' => $otherStore->id]);
        $component = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id'             => $customer->id,
            'order_type'              => OrderType::Specific,
            'status'                  => OrderStatus::Pending,
            'store_car_component_id'  => $component->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/orders/{$order->id}/reject")
            ->assertStatus(403);
    }

    public function test_provider_can_list_their_paid_general_orders_with_payout_math(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $customer = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id'       => $customer->id,
            'order_type'        => OrderType::General,
            'status'            => OrderStatus::Paid,
            'offered_price'     => 1000,
            'quantity'          => 2,
            'accepted_store_id' => $store->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/orders/paid')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.gross_amount', 2000)
            ->assertJsonPath('data.0.commission', 100)
            ->assertJsonPath('data.0.net_amount', 1900);
    }

    public function test_provider_does_not_see_other_providers_paid_orders(): void
    {
        $provider = User::factory()->provider()->create();
        $otherStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $customer = User::factory()->customer()->create();

        Order::factory()->create([
            'customer_id'       => $customer->id,
            'order_type'        => OrderType::General,
            'status'            => OrderStatus::Paid,
            'offered_price'     => 500,
            'quantity'          => 1,
            'accepted_store_id' => $otherStore->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/orders/paid')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_provider_paid_orders_exclude_completed_orders(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        $component = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        $customer = User::factory()->customer()->create();

        Order::factory()->create([
            'customer_id'             => $customer->id,
            'order_type'              => OrderType::Specific,
            'status'                  => OrderStatus::Completed,
            'offered_price'           => 800,
            'quantity'                => 1,
            'store_car_component_id'  => $component->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/orders/paid')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
