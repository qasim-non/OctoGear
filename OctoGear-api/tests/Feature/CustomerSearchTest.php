<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\CarCompany;
use App\Models\CarName;
use App\Models\CarSection;
use App\Models\City;
use App\Models\Color;
use App\Models\Component;
use App\Models\Country;
use App\Models\FuelType;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    private function authCustomer(): User
    {
        return User::factory()->create(['type' => 'customer']);
    }

    private function makeCity(string $name): City
    {
        return City::factory()->create(['name_en' => $name, 'name_ar' => $name]);
    }

    private function makeStore(array $overrides = []): Store
    {
        $owner = User::factory()->create(['type' => 'service provider']);

        return Store::factory()->create(array_merge(['user_id' => $owner->id], $overrides));
    }

    private function makeCatalog(): array
    {
        $section = CarSection::create(['name_en' => 'Engine', 'name_ar' => 'محرك']);
        $component = Component::create([
            'name_en' => 'Alternator',
            'name_ar' => 'مولد',
            'section_id' => $section->id,
        ]);
        $company = CarCompany::create(['name_en' => 'Toyota', 'name_ar' => 'تويوتا']);
        $carName = CarName::create([
            'name_en' => 'Camry',
            'name_ar' => 'كامري',
            'car_company_id' => $company->id,
        ]);
        $color = Color::create(['name_en' => 'White', 'name_ar' => 'أبيض']);
        $fuel = FuelType::create(['type_en' => 'Gasoline', 'type_ar' => 'بنزين']);

        return compact('section', 'component', 'company', 'carName', 'color', 'fuel');
    }

    private function makeCar(Store $store, CarName $carName, array $catalog): StoresCar
    {
        return StoresCar::factory()->create([
            'store_id'    => $store->id,
            'car_name_id' => $carName->id,
            'color_id'    => $catalog['color']->id,
            'fuel_type'   => $catalog['fuel']->id,
        ]);
    }

    private function addComponentToCar(StoresCar $car, int $componentId, array $overrides = []): StoreCarComponent
    {
        return StoreCarComponent::create(array_merge([
            'store_car_id'   => $car->id,
            'component_id'   => $componentId,
            'part_number'    => 'ALT-100',
            'description'    => 'Test alternator part',
            'price'          => 1200,
            'stock_quantity' => 5,
        ], $overrides));
    }

    public function test_filter_stores_by_name(): void
    {
        $this->makeStore(['name' => 'AlFaris Garage', 'nick_name' => 'AlFaris']);
        $this->makeStore(['name' => 'Another Garage', 'nick_name' => 'Other']);

        $response = $this->actingAs($this->authCustomer(), 'sanctum')
            ->getJson('/api/customer/stores?query=AlFaris');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'AlFaris Garage');
    }

    public function test_filter_stores_by_city(): void
    {
        $jeddah = $this->makeCity('Jeddah');
        $riyadh = $this->makeCity('Riyadh');
        $this->makeStore(['name' => 'Jeddah Store', 'city_id' => $jeddah->id]);
        $this->makeStore(['name' => 'Riyadh Store', 'city_id' => $riyadh->id]);

        $response = $this->actingAs($this->authCustomer(), 'sanctum')
            ->getJson("/api/customer/stores?city_id={$jeddah->id}");

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Jeddah Store');
    }

    public function test_filter_stores_by_company(): void
    {
        $toyota = CarCompany::create(['name_en' => 'Toyota', 'name_ar' => 'تويوتا']);
        $toyotaStore = $this->makeStore(['name' => 'Toyota Center']);
        $toyotaStore->companies()->attach($toyota->id);
        $this->makeStore(['name' => 'No Brand Store']);

        $response = $this->actingAs($this->authCustomer(), 'sanctum')
            ->getJson("/api/customer/stores?company_id={$toyota->id}");

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Toyota Center');
    }

    public function test_component_cars_returns_in_stock_cars_with_store_and_city(): void
    {
        $data = $this->makeCatalog();
        $city = $this->makeCity('Jeddah');
        $store = $this->makeStore(['city_id' => $city->id, 'name' => 'Jeddah Parts Store']);

        $car = $this->makeCar($store, $data['carName'], $data);
        $this->addComponentToCar($car, $data['component']->id);

        $response = $this->actingAs($this->authCustomer(), 'sanctum')
            ->getJson("/api/customer/component-cars?component_id={$data['component']->id}");

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);

        $item = $response->json('data.0');
        $this->assertSame($data['component']->id, $item['component']['component']['id']);
        $this->assertSame($store->id, $item['store']['id']);
        $this->assertSame($data['company']->id, $item['car_company']['id']);
    }

    public function test_component_cars_excludes_out_of_stock_components(): void
    {
        $data = $this->makeCatalog();
        $store = $this->makeStore(['name' => 'Store A']);
        $car = $this->makeCar($store, $data['carName'], $data);
        $this->addComponentToCar($car, $data['component']->id, ['stock_quantity' => 0]);

        $response = $this->actingAs($this->authCustomer(), 'sanctum')
            ->getJson("/api/customer/component-cars?component_id={$data['component']->id}");

        $response->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_component_cars_filters_by_city(): void
    {
        $data = $this->makeCatalog();
        $jeddah = $this->makeCity('Jeddah');
        $riyadh = $this->makeCity('Riyadh');

        $jeddahStore = $this->makeStore(['name' => 'Jeddah Store', 'city_id' => $jeddah->id]);
        $riyadhStore = $this->makeStore(['name' => 'Riyadh Store', 'city_id' => $riyadh->id]);

        $this->addComponentToCar(
            $this->makeCar($jeddahStore, $data['carName'], $data),
            $data['component']->id
        );
        $this->addComponentToCar(
            $this->makeCar($riyadhStore, $data['carName'], $data),
            $data['component']->id
        );

        $response = $this->actingAs($this->authCustomer(), 'sanctum')
            ->getJson("/api/customer/component-cars?component_id={$data['component']->id}&city_id={$jeddah->id}");

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.store.name', 'Jeddah Store');
    }

    public function test_component_cars_filters_by_car_name(): void
    {
        $data = $this->makeCatalog();
        $otherName = CarName::create([
            'name_en' => 'Corolla',
            'name_ar' => 'كورولا',
            'car_company_id' => $data['company']->id,
        ]);

        $store = $this->makeStore(['name' => 'Store A']);
        $otherStore = $this->makeStore(['name' => 'Store B']);

        $this->addComponentToCar(
            $this->makeCar($store, $data['carName'], $data),
            $data['component']->id
        );
        $this->addComponentToCar(
            $this->makeCar($otherStore, $otherName, $data),
            $data['component']->id
        );

        $response = $this->actingAs($this->authCustomer(), 'sanctum')
            ->getJson("/api/customer/component-cars?component_id={$data['component']->id}&car_name_id={$data['carName']->id}");

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.store.name', 'Store A');
    }

    public function test_stock_validation_rejects_quantity_exceeding_stock(): void
    {
        $data = $this->makeCatalog();
        $store = $this->makeStore(['name' => 'Store A']);
        $car = $this->makeCar($store, $data['carName'], $data);
        $component = $this->addComponentToCar($car, $data['component']->id, ['stock_quantity' => 2]);

        $response = $this->actingAs($this->authCustomer(), 'sanctum')
            ->postJson('/api/customer/orders', [
                'order_type' => 'specific',
                'store_car_component_id' => $component->id,
                'quantity' => 3,
            ]);

        $response->assertStatus(422);
    }

    public function test_stock_validation_rejects_out_of_stock_component(): void
    {
        $data = $this->makeCatalog();
        $store = $this->makeStore(['name' => 'Store A']);
        $car = $this->makeCar($store, $data['carName'], $data);
        $component = $this->addComponentToCar($car, $data['component']->id, ['stock_quantity' => 0]);

        $response = $this->actingAs($this->authCustomer(), 'sanctum')
            ->postJson('/api/customer/orders', [
                'order_type' => 'specific',
                'store_car_component_id' => $component->id,
                'quantity' => 1,
            ]);

        $response->assertStatus(422);
    }

    public function test_specific_order_with_valid_quantity_is_created(): void
    {
        $data = $this->makeCatalog();
        $store = $this->makeStore(['name' => 'Store A']);
        $car = $this->makeCar($store, $data['carName'], $data);
        $component = $this->addComponentToCar($car, $data['component']->id, ['stock_quantity' => 5]);

        $response = $this->actingAs($this->authCustomer(), 'sanctum')
            ->postJson('/api/customer/orders', [
                'order_type' => 'specific',
                'store_car_component_id' => $component->id,
                'quantity' => 2,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.order_type', OrderType::Specific->value)
            ->assertJsonPath('data.quantity', 2);
    }

    public function test_paying_a_specific_order_decrements_component_stock(): void
    {
        $data = $this->makeCatalog();
        $store = $this->makeStore(['name' => 'Store A']);
        $car = $this->makeCar($store, $data['carName'], $data);
        $component = $this->addComponentToCar($car, $data['component']->id, ['stock_quantity' => 5]);

        $customer = $this->authCustomer();
        $order = Order::factory()->create([
            'customer_id'             => $customer->id,
            'order_type'              => OrderType::Specific,
            'status'                  => OrderStatus::Negotiating,
            'offered_price'           => 450,
            'quantity'                => 2,
            'store_car_component_id'  => $component->id,
            'accepted_store_id'       => $store->id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/pay", [
                'payment_method' => 'credit_card',
                'card_token'     => 'tok_test_1',
            ])->assertOk();

        $this->assertSame(3, $component->fresh()->stock_quantity);
    }

    public function test_store_index_counts_only_completed_sales_via_accepted_store(): void
    {
        $data = $this->makeCatalog();
        $store = $this->makeStore(['name' => 'Store A']);
        $customer = $this->authCustomer();

        Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Completed,
            'quantity' => 3,
            'accepted_store_id' => $store->id,
        ]);

        Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Paid, // should NOT be counted
            'quantity' => 5,
            'accepted_store_id' => $store->id,
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/customer/stores');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $store->id)
            ->assertJsonPath('data.0.sold_quantity', 3);
    }

    public function test_store_index_counts_completed_sales_via_component_path(): void
    {
        $data = $this->makeCatalog();
        $store = $this->makeStore(['name' => 'Store A', 'nick_name' => 'Store A']);
        $car = $this->makeCar($store, $data['carName'], $data);
        $component = $this->addComponentToCar($car, $data['component']->id, ['stock_quantity' => 10]);
        $customer = $this->authCustomer();

        // Specific order (no accepted_store_id) completed => counts via component path
        Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::Specific,
            'status' => OrderStatus::Completed,
            'quantity' => 4,
            'store_car_component_id' => $component->id,
        ]);

        // Another completed order belonging to a DIFFERENT store => not counted
        $other = $this->makeStore(['name' => 'Store B']);
        $otherCar = $this->makeCar($other, $data['carName'], $data);
        $otherComponent = $this->addComponentToCar($otherCar, $data['component']->id, ['stock_quantity' => 10]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::Specific,
            'status' => OrderStatus::Completed,
            'quantity' => 7,
            'store_car_component_id' => $otherComponent->id,
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/customer/stores?query=Store A');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $store->id)
            ->assertJsonPath('data.0.sold_quantity', 4);
    }

    public function test_store_show_returns_sold_quantity(): void
    {
        $data = $this->makeCatalog();
        $store = $this->makeStore(['name' => 'Store A']);
        $customer = $this->authCustomer();

        Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
            'status' => OrderStatus::Completed,
            'quantity' => 2,
            'accepted_store_id' => $store->id,
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/stores/{$store->id}");

        $response->assertOk()
            ->assertJsonPath('data.sold_quantity', 2);
    }
}


