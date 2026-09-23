<?php

namespace Tests\Feature;

use App\Models\CarCompany;
use App\Models\CarName;
use App\Models\CarSection;
use App\Models\Component;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoreCarSection;
use App\Models\StoresCar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentCarsSearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeSearchableCar(User $provider, CarName $carName, CarSection $section): StoresCar
    {
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $car = StoresCar::factory()->create([
            'store_id' => $store->id,
            'car_name_id' => $carName->id,
        ]);

        StoreCarSection::factory()->create([
            'store_car_id' => $car->id,
            'section_id' => $section->id,
            'condition' => 'okay',
        ]);

        return $car;
    }

    private function addComponent(StoresCar $car, Component $component): void
    {
        StoreCarComponent::factory()->create([
            'store_car_id' => $car->id,
            'component_id' => $component->id,
            'stock_quantity' => 5,
        ]);
    }

    private function makeCatalog(): array
    {
        $section = CarSection::factory()->create();
        $component = Component::factory()->create(['section_id' => $section->id]);
        $toyota = CarCompany::create(['name_en' => 'Toyota', 'name_ar' => 'تويوتا']);
        $honda = CarCompany::create(['name_en' => 'Honda', 'name_ar' => 'هوندا']);
        $camry = CarName::create(['name_en' => 'Camry', 'name_ar' => 'كامري', 'car_company_id' => $toyota->id]);
        $civic = CarName::create(['name_en' => 'Civic', 'name_ar' => 'سيفيك', 'car_company_id' => $honda->id]);

        return compact('section', 'component', 'toyota', 'honda', 'camry', 'civic');
    }

    public function test_customer_can_search_component_cars(): void
    {
        $data = $this->makeCatalog();
        $car = $this->makeSearchableCar(User::factory()->provider()->create(), $data['camry'], $data['section']);
        $this->addComponent($car, $data['component']);

        $this->actingAs(User::factory()->customer()->create(), 'sanctum')
            ->getJson('/api/component-cars?component_id='.$data['component']->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.car.id', $car->id);
    }

    public function test_provider_can_search_component_cars(): void
    {
        $data = $this->makeCatalog();
        $car = $this->makeSearchableCar(User::factory()->provider()->create(), $data['camry'], $data['section']);
        $this->addComponent($car, $data['component']);

        $this->actingAs(User::factory()->provider()->create(), 'sanctum')
            ->getJson('/api/component-cars?component_id='.$data['component']->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.car.id', $car->id);
    }

    public function test_component_id_is_required(): void
    {
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')
            ->getJson('/api/component-cars')
            ->assertStatus(422)
            ->assertJsonValidationErrors('component_id');
    }

    public function test_search_filters_by_car_company(): void
    {
        $data = $this->makeCatalog();
        $provider = User::factory()->provider()->create();

        $camryCar = $this->makeSearchableCar($provider, $data['camry'], $data['section']);
        $this->addComponent($camryCar, $data['component']);

        $civicCar = $this->makeSearchableCar($provider, $data['civic'], $data['section']);
        $this->addComponent($civicCar, $data['component']);

        $this->actingAs(User::factory()->customer()->create(), 'sanctum')
            ->getJson('/api/component-cars?component_id='.$data['component']->id.'&car_company_id='.$data['toyota']->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.car.id', $camryCar->id);
    }
}
