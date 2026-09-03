<?php

namespace Tests\Feature;

use App\Models\Component;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderStoreCarComponentTest extends TestCase
{
    use RefreshDatabase;

    private function makeProviderAndStore(): array
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);

        return [$provider, $store, $car];
    }

    public function test_provider_can_list_components_of_their_store_car(): void
    {
        [$provider, $store, $car] = $this->makeProviderAndStore();
        StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        StoreCarComponent::factory()->create(['store_car_id' => $car->id]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/store/{$store->id}/cars/{$car->id}/components")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_cannot_list_components_of_car_in_another_store(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $otherStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $car = StoresCar::factory()->create(['store_id' => $otherStore->id]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/store/{$store->id}/cars/{$car->id}/components")
            ->assertStatus(404);
    }

    public function test_provider_can_create_component_for_their_car(): void
    {
        [$provider, $store, $car] = $this->makeProviderAndStore();
        $component = Component::factory()->create();

        $payload = [
            'component_id'    => $component->id,
            'part_number'     => 'BRK-001',
            'description'     => 'Front brake pads',
            'price'           => 150,
            'stock_quantity'  => 20,
            'warranty_months' => 12,
        ];

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/store/{$store->id}/cars/{$car->id}/components", $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.part_number', 'BRK-001')
            ->assertJsonPath('data.price', 150)
            ->assertJsonPath('data.stock_quantity', 20);

        $this->assertDatabaseHas('store_car_components', [
            'store_car_id'   => $car->id,
            'component_id'   => $component->id,
            'part_number'    => 'BRK-001',
        ]);
    }

    public function test_cannot_create_component_for_car_in_another_store(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $otherStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $car = StoresCar::factory()->create(['store_id' => $otherStore->id]);
        $component = Component::factory()->create();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/store/{$store->id}/cars/{$car->id}/components", [
                'component_id'    => $component->id,
                'part_number'     => 'BRK-001',
                'price'           => 150,
                'stock_quantity'  => 20,
            ])
            ->assertStatus(404);
    }

    public function test_create_component_requires_required_fields(): void
    {
        [$provider, $store, $car] = $this->makeProviderAndStore();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/store/{$store->id}/cars/{$car->id}/components", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['component_id', 'part_number', 'price', 'stock_quantity']);
    }

    public function test_provider_can_view_a_component(): void
    {
        [$provider, $store, $car] = $this->makeProviderAndStore();
        $component = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/store/{$store->id}/cars/{$car->id}/components/{$component->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $component->id);
    }

    public function test_provider_can_update_a_component(): void
    {
        [$provider, $store, $car] = $this->makeProviderAndStore();
        $component = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);

        $this->actingAs($provider, 'sanctum')
            ->putJson("/api/provider/store/{$store->id}/cars/{$car->id}/components/{$component->id}", [
                'price'          => 250,
                'stock_quantity' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('data.price', 250)
            ->assertJsonPath('data.stock_quantity', 5);

        $this->assertDatabaseHas('store_car_components', ['id' => $component->id, 'price' => 250]);
    }

    public function test_provider_can_delete_a_component(): void
    {
        [$provider, $store, $car] = $this->makeProviderAndStore();
        $component = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);

        $this->actingAs($provider, 'sanctum')
            ->deleteJson("/api/provider/store/{$store->id}/cars/{$car->id}/components/{$component->id}")
            ->assertOk();

        $this->assertSoftDeleted('store_car_components', ['id' => $component->id]);
    }

    public function test_provider_can_batch_create_components(): void
    {
        [$provider, $store, $car] = $this->makeProviderAndStore();
        $compA = Component::factory()->create();
        $compB = Component::factory()->create();

        $payload = [
            'components' => [
                [
                    'component_id'    => $compA->id,
                    'part_number'     => 'BRK-001',
                    'price'           => 100,
                    'stock_quantity'  => 10,
                ],
                [
                    'component_id'    => $compB->id,
                    'part_number'     => 'OIL-002',
                    'description'     => 'Oil filter',
                    'price'           => 50,
                    'stock_quantity'  => 25,
                    'warranty_months' => 6,
                ],
            ],
        ];

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/store/{$store->id}/cars/{$car->id}/components/batch", $payload)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('store_car_components', [
            'store_car_id'  => $car->id,
            'component_id'  => $compA->id,
            'part_number'   => 'BRK-001',
        ]);
        $this->assertDatabaseHas('store_car_components', [
            'store_car_id'  => $car->id,
            'component_id'  => $compB->id,
            'part_number'   => 'OIL-002',
        ]);
    }

    public function test_batch_create_requires_at_least_one_component(): void
    {
        [$provider, $store, $car] = $this->makeProviderAndStore();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/store/{$store->id}/cars/{$car->id}/components/batch", [
                'components' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('components');
    }

    public function test_batch_create_validates_each_component(): void
    {
        [$provider, $store, $car] = $this->makeProviderAndStore();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/store/{$store->id}/cars/{$car->id}/components/batch", [
                'components' => [
                    ['component_id' => 999999, 'price' => -1],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'components.0.component_id',
                'components.0.part_number',
                'components.0.price',
                'components.0.stock_quantity',
            ]);
    }

    public function test_cannot_batch_create_for_another_store_car(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $otherStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $car = StoresCar::factory()->create(['store_id' => $otherStore->id]);
        $component = Component::factory()->create();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/store/{$store->id}/cars/{$car->id}/components/batch", [
                'components' => [
                    [
                        'component_id'    => $component->id,
                        'part_number'     => 'X-001',
                        'price'           => 100,
                        'stock_quantity'  => 1,
                    ],
                ],
            ])
            ->assertStatus(404);
    }
}
