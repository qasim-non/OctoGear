<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderGeneralDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_cities_is_explicit_and_selection_can_be_cleared(): void
    {
        $provider = User::factory()->provider()->create();
        $cities = City::factory()->count(2)->create();
        Store::factory()->create(['user_id' => $provider->id, 'city_id' => $cities[0]->id]);
        foreach ($cities as $city) {
            Order::factory()->create(['customer_id' => User::factory()->customer()->create(['city_id' => $city->id])->id]);
        }
        $this->actingAs($provider, 'sanctum');
        $this->getJson('/api/provider/orders/general')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/provider/orders/general?city_id='.$cities[1]->id)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.city.id', $cities[1]->id);
        $this->getJson('/api/provider/orders/general?city_id=')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/provider/orders/general')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_list_and_detail_expose_localized_city_but_not_customer_details_or_other_offers(): void
    {
        $provider = User::factory()->provider()->create();
        $city = City::factory()->create(['name_en' => 'Jeddah', 'name_ar' => 'جدة']);
        $customer = User::factory()->customer()->create(['city_id' => $city->id]);
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $mine = Store::factory()->create(['user_id' => $provider->id]);
        $other = Store::factory()->create();
        $myOffer = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $mine->id]);
        OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $other->id]);
        $this->actingAs($provider, 'sanctum');
        $this->getJson('/api/provider/orders/general', ['Accept-Language' => 'en'])
            ->assertOk()->assertJsonPath('data.0.city.name', 'Jeddah')
            ->assertJsonCount(1, 'data.0.offers')->assertJsonPath('data.0.offers.0.id', $myOffer->id)
            ->assertJsonMissingPath('data.0.customer')->assertJsonMissingPath('data.0.customer_id');
        $this->getJson('/api/provider/orders/'.$order->id, ['Accept-Language' => 'ar'])
            ->assertOk()->assertJsonPath('data.city.name', 'جدة')
            ->assertJsonCount(1, 'data.offers')->assertJsonMissingPath('data.customer');
    }

    public function test_pages_have_stable_order_and_exclude_closed_or_direct_requests(): void
    {
        $provider = User::factory()->provider()->create();
        $customer = User::factory()->customer()->create();
        $orders = Order::factory()->count(18)->create(['customer_id' => $customer->id, 'created_at' => now()->startOfMinute()]);
        Order::factory()->create(['customer_id' => $customer->id, 'status' => OrderStatus::Completed]);
        Order::factory()->specific()->create(['customer_id' => $customer->id]);
        $this->actingAs($provider, 'sanctum');
        $first = $this->getJson('/api/provider/orders/general')->assertOk()->assertJsonPath('meta.total', 18)->json('data');
        $second = $this->getJson('/api/provider/orders/general?page=2')->assertOk()->assertJsonCount(3, 'data')->json('data');
        $this->assertSame($orders->pluck('id')->sortDesc()->values()->all(), array_column([...$first, ...$second], 'id'));
        foreach (['0', '-1', 'abc', '1.5'] as $page) {
            $this->getJson('/api/provider/orders/general?page='.$page)->assertUnprocessable();
        }
        foreach (['0', '-1', 'abc', '999999'] as $city) {
            $this->getJson('/api/provider/orders/general?city_id='.$city)->assertUnprocessable();
        }
    }

    public function test_general_discovery_still_requires_a_provider_session(): void
    {
        $this->getJson('/api/provider/orders/general')->assertUnauthorized();
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')
            ->getJson('/api/provider/orders/general')->assertForbidden();
    }
}
