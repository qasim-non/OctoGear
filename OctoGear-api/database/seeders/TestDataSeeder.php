<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\UserType;
use App\Models\Admin;
use App\Models\CarModel;
use App\Models\CarName;
use App\Models\Color;
use App\Models\Component;
use App\Models\Conversation;
use App\Models\CustomerCar;
use App\Models\FuelType;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Payment;
use App\Models\Rating;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmins();
        $customers = $this->seedCustomers();
        $providers = $this->seedProviders();
        $customerCars = $this->seedCustomerCars($customers);
        $stores = $this->seedStores($providers);
        $storesCars = $this->seedStoresCars($stores);
        $storeCarComponents = $this->seedStoreCarComponents($storesCars);
        $orders = $this->seedOrders($customers, $storeCarComponents);
        $this->seedOrderOffers($orders, $stores);
        $this->seedPayments($orders);
        $this->seedRatings($customers, $stores, $orders);
        $this->seedConversations($customers, $providers);

        $this->command->info('Test data seeded successfully!');
    }

    private function seedAdmins(): void
    {
        Admin::factory()->count(2)->create();
        $this->command->info('Seeded 2 test admins');
    }

    private function seedCustomers(): array
    {
        $cities = $this->getRandomSaudiCities(4)->pluck('id')->all();

        $names = ['أحمد محمد', 'خالد العلي', 'سعد الشمري', 'عبدالله الحربي'];

        $customers = [];
        for ($i = 0; $i < 4; $i++) {
            $customers[] = User::factory()->customer()->create([
                'full_name' => $names[$i],
                'city_id' => $cities[$i],
            ]);
        }

        $this->command->info('Seeded 4 test customers');
        return $customers;
    }

    private function seedProviders(): array
    {
        $cities = $this->getRandomSaudiCities(3)->pluck('id')->all();

        $names = ['فهد العتيبي', 'نواف القحطاني', 'عبدالرحمن السبيعي'];

        $providers = [];
        for ($i = 0; $i < 3; $i++) {
            $providers[] = User::factory()->provider()->create([
                'full_name' => $names[$i],
                'city_id' => $cities[$i],
            ]);
        }

        $this->command->info('Seeded 3 test providers');
        return $providers;
    }

    private function seedCustomerCars(array $customers): array
    {
        $carNameIds = CarName::inRandomOrder()->take(4)->pluck('id')->all();
        $colorIds = Color::inRandomOrder()->take(4)->pluck('id')->all();
        $fuelTypeIds = FuelType::inRandomOrder()->take(4)->pluck('id')->all();

        $cars = [];
        foreach ($customers as $i => $customer) {
            $cars[] = CustomerCar::factory()->create([
                'customer_id' => $customer->id,
                'car_name_id' => $carNameIds[$i],
                'color_id' => $colorIds[$i],
                'fuel_type' => $fuelTypeIds[$i],
            ]);
        }

        $this->command->info('Seeded 4 customer cars');
        return $cars;
    }

    private function seedStores(array $providers): array
    {
        $cityIds = $this->getRandomSaudiCities(3)->pluck('id')->all();

        $stores = [];
        foreach ($providers as $i => $provider) {
            $stores[] = Store::factory()->create([
                'user_id' => $provider->id,
                'city_id' => $cityIds[$i],
            ]);
        }

        $this->command->info('Seeded 3 test stores');
        return $stores;
    }

    private function seedStoresCars(array $stores): array
    {
        $carNameIds = CarName::inRandomOrder()->take(5)->pluck('id')->all();
        $colorIds = Color::inRandomOrder()->take(5)->pluck('id')->all();
        $fuelTypeIds = FuelType::inRandomOrder()->take(5)->pluck('id')->all();

        $storesCars = [];
        $idx = 0;
        foreach ($stores as $i => $store) {
            $count = ($i === 0) ? 2 : 1;
            for ($j = 0; $j < $count; $j++) {
                $pos = $idx % 5;
                $storesCars[] = StoresCar::factory()->create([
                    'store_id' => $store->id,
                    'car_name_id' => $carNameIds[$pos],
                    'color_id' => $colorIds[$pos],
                    'fuel_type' => $fuelTypeIds[$pos],
                ]);
                $idx++;
            }
        }

        $this->command->info('Seeded ' . count($storesCars) . ' store cars');
        return $storesCars;
    }

    private function seedStoreCarComponents(array $storesCars): array
    {
        $componentIds = Component::inRandomOrder()->take(6)->pluck('id')->all();
        $all = [];
        $count = 0;

        foreach ($storesCars as $storesCar) {
            $componentCount = fake()->numberBetween(1, 2);
            for ($i = 0; $i < $componentCount && $i < count($componentIds); $i++) {
                $all[] = StoreCarComponent::factory()->create([
                    'store_car_id' => $storesCar->id,
                    'component_id' => $componentIds[$i],
                ]);
                $count++;
            }
        }

        $this->command->info("Seeded {$count} store car components");
        return $all;
    }

    private function seedOrders(array $customers, array $storeCarComponents): array
    {
        $modelIds = CarModel::inRandomOrder()->take(3)->pluck('id')->all();

        $orders = [];

        // 2 general orders (pending, negotiating)
        $orders[] = Order::factory()->general()->create([
            'customer_id' => $customers[0]->id,
            'model_id' => $modelIds[0],
            'status' => OrderStatus::Pending,
        ]);

        $orders[] = Order::factory()->general()->create([
            'customer_id' => $customers[1]->id,
            'model_id' => $modelIds[1],
            'status' => OrderStatus::Negotiating,
        ]);

        // 2 specific orders (pending, completed)
        $orders[] = Order::factory()->specific()->create([
            'customer_id' => $customers[2]->id,
            'store_car_component_id' => $storeCarComponents[0]->id,
            'model_id' => $modelIds[2],
            'status' => OrderStatus::Pending,
        ]);

        $orders[] = Order::factory()->specific()->create([
            'customer_id' => $customers[0]->id,
            'store_car_component_id' => $storeCarComponents[1]->id ?? $storeCarComponents[0]->id,
            'model_id' => $modelIds[0],
            'status' => OrderStatus::Completed,
            'accepted_store_id' => $storeCarComponents[1]->storeCar->store_id ?? $storeCarComponents[0]->storeCar->store_id,
        ]);

        // 1 cancelled order
        $orders[] = Order::factory()->general()->create([
            'customer_id' => $customers[3]->id,
            'model_id' => $modelIds[1],
            'status' => OrderStatus::Cancelled,
        ]);

        $this->command->info('Seeded 5 orders (2 general, 3 specific)');
        return $orders;
    }

    private function seedOrderOffers(array $orders, array $stores): void
    {
        // Offer on negotiating general order
        OrderOffer::factory()->create([
            'order_id' => $orders[1]->id,
            'store_id' => $stores[0]->id,
        ]);

        // Offer on pending specific order
        OrderOffer::factory()->create([
            'order_id' => $orders[2]->id,
            'store_id' => $stores[1]->id,
        ]);

        // Offer on completed order
        OrderOffer::factory()->create([
            'order_id' => $orders[3]->id,
            'store_id' => $stores[0]->id,
        ]);

        $this->command->info('Seeded 3 order offers');
    }

    private function seedPayments(array $orders): void
    {
        Payment::factory()->paid()->create([
            'order_id' => $orders[3]->id,
        ]);

        $this->command->info('Seeded 1 payment');
    }

    private function seedRatings(array $customers, array $stores, array $orders): void
    {
        Rating::factory()->create([
            'customer_id' => $customers[0]->id,
            'store_id' => $stores[0]->id,
            'order_id' => $orders[3]->id,
        ]);

        $this->command->info('Seeded 1 rating');
    }

    private function seedConversations(array $customers, array $providers): void
    {
        $pairs = [
            [$customers[0]->id, $providers[0]->id],
            [$customers[1]->id, $providers[1]->id],
        ];

        foreach ($pairs as [$customerId, $providerId]) {
            $conversation = Conversation::factory()->create([
                'customer_id' => $customerId,
                'provider_id' => $providerId,
            ]);

            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $customerId,
                'is_read' => true,
            ]);

            Message::factory()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $providerId,
                'is_read' => fake()->boolean(80),
            ]);
        }

        $this->command->info('Seeded 2 conversations with 4 messages');
    }

    private function getRandomSaudiCities(int $count): \Illuminate\Support\Collection
    {
        return \App\Models\City::where(
            'country_id',
            \App\Models\Country::where('name_en', 'Saudi Arabia')->first()->id
        )
            ->inRandomOrder()
            ->take($count)
            ->get();
    }
}
