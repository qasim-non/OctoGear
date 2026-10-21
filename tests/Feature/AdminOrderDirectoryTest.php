<?php

namespace Tests\Feature;

use App\Http\Resources\AdminOrderResource;
use App\Models\Admin;
use App\Models\City;
use App\Models\Order;
use App\Models\StoreCarComponent;
use App\Models\User;
use App\Services\AdminOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminOrderDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_combined_filters_use_customer_city_and_inclusive_utc_dates(): void
    {
        $customer = User::factory()->create(['full_name' => 'Customer 0']);
        $inside = Order::factory()->create(['customer_id' => $customer->id, 'order_type' => 'general', 'status' => 'pending', 'created_at' => '2026-10-21 23:59:59']);
        Order::factory()->create(['customer_id' => $customer->id, 'created_at' => '2026-10-22 00:00:00']);
        $query = http_build_query(['customer' => '0', 'mobile' => $customer->mobile, 'city_id' => $customer->city_id, 'type' => 'general', 'status' => 'pending', 'date_from' => '2026-10-21', 'date_to' => '2026-10-21']);
        $this->actingAs(Admin::factory()->create(), 'admin')->getJson('/api/admin/orders?'.$query)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $inside->id);
        $this->getJson('/api/admin/orders?date_to=2026-10-21')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/orders?order_id='.$inside->id)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_filters_validate_and_pages_are_stable(): void
    {
        $city = City::factory()->create();
        $city->delete();
        $this->actingAs(Admin::factory()->create(), 'admin');
        foreach (['page=0', 'city_id='.$city->id, 'order_id=-1', 'date_from=2026-02-30', 'date_from=2026-10-21&date_to=2026-10-20'] as $query) {
            $this->getJson('/api/admin/orders?'.$query)->assertUnprocessable();
        }
        $orders = Order::factory()->count(16)->create(['customer_id' => User::factory(), 'created_at' => '2026-10-21 12:00:00']);
        $this->getJson('/api/admin/orders')->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('data.0.id', $orders->last()->id);
        $this->getJson('/api/admin/orders?page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $orders->first()->id);
    }

    public function test_specific_details_have_localized_part_vehicle_and_cities_without_resource_queries(): void
    {
        $part = StoreCarComponent::factory()->create();
        $part->component->update(['name_en' => 'Mirror', 'name_ar' => 'مرآة']);
        $order = Order::factory()->create(['customer_id' => User::factory(), 'order_type' => 'specific', 'store_car_component_id' => $part->id, 'requested_unit_price' => 12345]);
        $this->actingAs(Admin::factory()->create(), 'admin');
        foreach (['en' => 'Mirror', 'ar' => 'مرآة'] as $locale => $label) {
            $this->getJson('/api/admin/orders/'.$order->id, ['Accept-Language' => $locale])->assertOk()
                ->assertJsonPath('data.store_car_component.component_name', $label)->assertJsonPath('data.requested_unit_price', 12345)
                ->assertJsonPath('data.currency', 'SAR')->assertJsonPath('data.price_scale', 100)
                ->assertJsonPath('data.accepted_store.city.id', $part->store->city_id);
        }
        $prepared = app(AdminOrderService::class)->show($order);
        DB::enableQueryLog();
        DB::flushQueryLog();
        (new AdminOrderResource($prepared))->response()->getData(true);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
        $order->customer->city->delete();
        $this->getJson('/api/admin/orders/'.$order->id)->assertOk()->assertJsonPath('data.customer.city', null);
    }
}
