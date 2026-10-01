<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Models\CarName;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CustomerOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_customer_scoped_filtered_before_pagination_and_stably_ordered(): void
    {
        $customer = User::factory()->customer()->create();
        Order::factory()->specific()->count(17)->create(['customer_id' => $customer->id, 'created_at' => now()]);
        Order::factory()->general()->create(['customer_id' => $customer->id]);
        Order::factory()->specific()->create(['customer_id' => User::factory()->customer()->create()->id]);
        $this->actingAs($customer, 'sanctum');
        $first = $this->getJson('/api/customer/orders?order_type=specific')->assertOk()
            ->assertJsonPath('meta.total', 17)->assertJsonCount(15, 'data');
        $this->assertSame(Order::where('customer_id', $customer->id)->where('order_type', 'specific')->max('id'), $first->json('data.0.id'));
        $second = $this->getJson('/api/customer/orders?order_type=specific&page=2')->assertOk()->assertJsonCount(2, 'data');
        $this->assertEmpty(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        $this->getJson('/api/customer/orders?order_type=general')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.order_type', 'general');
        $this->getJson('/api/customer/orders')->assertOk()->assertJsonPath('meta.total', 18);
        $this->getJson('/api/customer/orders?order_type=other')->assertUnprocessable();
        $this->getJson('/api/customer/orders?page=0')->assertUnprocessable();
    }

    public function test_specific_history_has_localized_part_car_store_and_currency(): void
    {
        $customer = User::factory()->customer()->create();
        $part = StoreCarComponent::factory()->create(['price' => 12345]);
        $part->component->update(['name_en' => 'Left wheel', 'name_ar' => 'العجلة اليسرى']);
        $part->storeCar->carName->update(['name_en' => 'Camry', 'name_ar' => 'كامري']);
        $order = Order::factory()->specific()->create(['customer_id' => $customer->id, 'store_car_component_id' => $part->id]);
        $this->actingAs($customer, 'sanctum')->getJson('/api/customer/orders/'.$order->id, ['Accept-Language' => 'en'])->assertOk()
            ->assertJsonPath('data.part_name', 'Left wheel')->assertJsonPath('data.car_name', 'Camry')
            ->assertJsonPath('data.store_car_component.price', 12345)->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.price_scale', 100)->assertJsonPath('data.requested_unit_price', null)
            ->assertJsonPath('data.paid_amount', null)->assertJsonPath('data.vehicle_details', null)
            ->assertJsonMissingPath('data.payment')->assertJsonMissingPath('data.customer_id');
        $this->getJson('/api/customer/orders/'.$order->id, ['Accept-Language' => 'ar'])->assertOk()
            ->assertJsonPath('data.part_name', 'العجلة اليسرى')->assertJsonPath('data.car_name', 'كامري');
    }

    public function test_general_history_handles_no_offers_and_then_selected_store_and_offers(): void
    {
        $customer = User::factory()->customer()->create();
        $carName = CarName::factory()->create(['name_en' => 'Sunny']);
        $order = Order::factory()->general()->create(['customer_id' => $customer->id]);
        $order->vehicleDetails()->create([
            'car_name_id' => $carName->id, 'car_name_en' => 'Sunny', 'car_name_ar' => 'صني', 'manufacturing_year' => 2020,
            'company_name_en' => $carName->carCompany->name_en, 'company_name_ar' => $carName->carCompany->name_ar,
            'color_name_en' => 'White', 'color_name_ar' => 'أبيض', 'fuel_type_en' => 'Petrol', 'fuel_type_ar' => 'بنزين',
        ]);
        $this->actingAs($customer, 'sanctum')->getJson('/api/customer/orders/'.$order->id, ['Accept-Language' => 'en'])->assertOk()
            ->assertJsonPath('data.store_car_component', null)->assertJsonPath('data.vehicle_details.manufacturing_year', 2020)
            ->assertJsonPath('data.car_name', 'Sunny')->assertJsonPath('data.accepted_store', null)
            ->assertJsonPath('data.offers_count', 0)->assertJsonCount(0, 'data.offers');
        $store = Store::factory()->create();
        OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $store->id, 'price' => 15000]);
        $order->update(['accepted_store_id' => $store->id, 'offered_price' => 15000, 'status' => OrderStatus::Negotiating]);
        $this->getJson('/api/customer/orders/'.$order->id)->assertOk()
            ->assertJsonPath('data.accepted_store.id', $store->id)->assertJsonPath('data.offers_count', 1)
            ->assertJsonPath('data.offers.0.price', 15000)->assertJsonPath('data.offered_price', 15000);
    }

    public function test_retained_history_survives_deleted_references_and_reads_actual_paid_amount(): void
    {
        $customer = User::factory()->customer()->create();
        $part = StoreCarComponent::factory()->create();
        $order = Order::factory()->specific()->create(['customer_id' => $customer->id, 'store_car_component_id' => $part->id, 'status' => OrderStatus::Paid]);
        Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 42000]);
        $part->delete();
        $this->actingAs($customer, 'sanctum')->getJson('/api/customer/orders/'.$order->id)->assertOk()
            ->assertJsonPath('data.store_car_component', null)->assertJsonPath('data.part_name', null)
            ->assertJsonPath('data.paid_amount', 42000);
        $general = Order::factory()->general()->create(['customer_id' => $customer->id]);
        $store = Store::factory()->create();
        OrderOffer::factory()->create(['order_id' => $general->id, 'store_id' => $store->id]);
        $store->delete();
        $this->getJson('/api/customer/orders/'.$general->id)->assertOk()->assertJsonPath('data.offers.0.store', null);
    }

    public function test_fixed_price_snapshot_is_server_owned_and_survives_catalog_edits_and_retry(): void
    {
        Event::fake([OrderCreated::class]);
        $part = StoreCarComponent::factory()->create(['price' => 12345, 'stock_quantity' => 5]);
        $customer = User::factory()->customer()->create();
        $headers = ['Idempotency-Key' => 'f43d07a5-5e78-4f24-bf07-2ef96f6d3ac4'];
        $payload = ['order_type' => 'specific', 'quantity' => 2, 'store_car_component_id' => $part->id, 'requested_unit_price' => 1];
        $id = $this->actingAs($customer, 'sanctum')->postJson('/api/customer/orders', $payload, $headers)->assertCreated()->json('data.id');
        $part->update(['price' => 99999]);
        $this->postJson('/api/customer/orders', $payload, $headers)->assertCreated()->assertJsonPath('data.id', $id);
        $this->getJson('/api/customer/orders/'.$id)->assertOk()->assertJsonPath('data.requested_unit_price', 12345)
            ->assertJsonPath('data.store_car_component.price', 99999)->assertJsonPath('data.offered_price', null);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_customer_cannot_read_another_customers_order_and_provider_cannot_use_history(): void
    {
        $order = Order::factory()->general()->create(['customer_id' => User::factory()->customer()->create()->id]);
        $this->getJson('/api/customer/orders')->assertUnauthorized();
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')->getJson('/api/customer/orders/'.$order->id)->assertForbidden();
        $this->actingAs(User::factory()->provider()->create(), 'sanctum')->getJson('/api/customer/orders')->assertForbidden();
    }
}
