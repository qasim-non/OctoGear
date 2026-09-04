<?php

namespace Tests\Unit;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\RequestStatus;
use App\Events\OrderCompleted;
use App\Events\OrderCreated;
use App\Exceptions\BusinessRuleException;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;
use App\Models\User;
use App\Services\OrderOfferService;
use App\Services\OrderService;
use App\Services\OtpService;
use App\Services\StoreRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_for_customer_builds_a_pending_order_and_fires_event(): void
    {
        Event::fake([OrderCreated::class]);
        $customer = User::factory()->customer()->create();
        $store = Store::factory()->create();
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        $component = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);

        $order = app(OrderService::class)->createForCustomer($customer, [
            'store_car_component_id' => $component->id,
            'order_type' => OrderType::General,
            'quantity' => 2,
        ]);

        $this->assertSame(OrderStatus::Pending, $order->status);
        Event::assertDispatched(OrderCreated::class);
    }

    public function test_accept_offer_moves_order_to_negotiating(): void
    {
        $customer = User::factory()->customer()->create();
        $store = Store::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Pending,
        ]);
        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'price' => 999,
        ]);

        app(OrderService::class)->acceptOffer($order, $offer);

        $this->assertSame(OrderStatus::Negotiating, $order->status);
        $this->assertSame(999, (int) $order->offered_price);
        $this->assertSame($store->id, $order->accepted_store_id);
    }

    public function test_accept_offer_rejects_illegal_transition(): void
    {
        $customer = User::factory()->customer()->create();
        $store = Store::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Completed,
        ]);
        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
        ]);

        $this->expectException(BusinessRuleException::class);

        app(OrderService::class)->acceptOffer($order, $offer);
    }

    public function test_cancel_removes_outstanding_offers(): void
    {
        $customer = User::factory()->customer()->create();
        $store = Store::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Pending,
        ]);
        OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $store->id]);

        app(OrderService::class)->cancel($order);

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(0, $order->offers()->count());
    }

    public function test_complete_fires_completed_event(): void
    {
        Event::fake([OrderCompleted::class]);
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Paid,
        ]);

        app(OrderService::class)->complete($order);

        $this->assertSame(OrderStatus::Completed, $order->status);
        Event::assertDispatched(OrderCompleted::class);
    }

    public function test_reject_only_allowed_for_pending_specific_orders(): void
    {
        $customer = User::factory()->customer()->create();
        $general = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Pending,
        ]);

        try {
            app(OrderService::class)->reject($general);
            $this->fail('Expected BusinessRuleException for a general order.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('auth.validation.order.cannot_reject_general', $e->messageKey());
        }

        $specific = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::Specific,
            'status' => OrderStatus::Pending,
        ]);

        app(OrderService::class)->reject($specific);

        $this->assertSame(OrderStatus::Rejected, $specific->status);
    }

    public function test_order_offer_dedupe_per_store(): void
    {
        $customer = User::factory()->customer()->create();
        $store = Store::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Pending,
        ]);
        OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $store->id]);

        $this->expectException(BusinessRuleException::class);

        app(OrderOfferService::class)->create($order, ['store_id' => $store->id, 'price' => 100]);
    }

    public function test_only_pending_offers_can_be_edited(): void
    {
        $customer = User::factory()->customer()->create();
        $store = Store::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Pending,
        ]);
        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id,
            'store_id' => $store->id,
            'status' => OfferStatus::Rejected,
        ]);

        $this->expectException(BusinessRuleException::class);

        app(OrderOfferService::class)->update($offer, ['price' => 200]);
    }

    public function test_store_request_requires_a_valid_one_time_token(): void
    {
        $provider = User::factory()->provider()->create();

        $service = app(StoreRequestService::class);
        $otp = app(OtpService::class);

        $city = City::factory()->create();

        $token = $otp->createPendingToken('store', '0501234567');

        $payload = fn () => [
            'temp_token' => $token,
            'name' => 'My Auto Shop',
            'nick_name' => 'Auto',
            'employee_name' => 'John',
            'url_location' => 'https://maps.example/shop',
            'commercial_registration_number' => '12345',
            'commercial_registration_picture' => 'https://example.com/reg.png',
            'city_id' => $city->id,
        ];

        $request = $service->becomeProvider($provider, $payload());
        $this->assertSame('0501234567', $request->mobile);
        $this->assertSame(RequestStatus::Pending, $request->request_status);

        // A consumed token cannot be reused.
        $this->expectException(BusinessRuleException::class);

        $service->becomeProvider($provider, $payload());
    }
}
