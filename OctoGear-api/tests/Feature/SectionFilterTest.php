<?php

namespace Tests\Feature;

use App\Models\CarSection;
use App\Models\Component;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoreCarSection;
use App\Models\StoresCar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_cars_search_filters_by_section_ok(): void
    {
        $customer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);

        $section = CarSection::factory()->create();
        $component = Component::factory()->create(['section_id' => $section->id]);

        // Car A: section okay → should appear
        $carA = StoresCar::factory()->create(['store_id' => $store->id]);
        StoreCarSection::factory()->create([
            'store_car_id' => $carA->id,
            'section_id'   => $section->id,
            'condition'    => 'okay',
        ]);
        StoreCarComponent::factory()->create([
            'store_car_id' => $carA->id,
            'component_id' => $component->id,
            'stock_quantity' => 5,
        ]);

        // Car B: section damaged → should NOT appear
        $carB = StoresCar::factory()->create(['store_id' => $store->id]);
        StoreCarSection::factory()->create([
            'store_car_id' => $carB->id,
            'section_id'   => $section->id,
            'condition'    => 'damaged',
        ]);
        StoreCarComponent::factory()->create([
            'store_car_id' => $carB->id,
            'component_id' => $component->id,
            'stock_quantity' => 5,
        ]);

        // Car C: no section record → should NOT appear
        $carC = StoresCar::factory()->create(['store_id' => $store->id]);
        StoreCarComponent::factory()->create([
            'store_car_id' => $carC->id,
            'component_id' => $component->id,
            'stock_quantity' => 5,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/customer/component-cars?component_id=' . $component->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.car.id', $carA->id);
    }
}
