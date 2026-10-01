<?php

namespace Tests\Feature;

use App\Models\CarName;
use App\Models\CarSection;
use App\Models\Color;
use App\Models\FuelType;
use App\Models\Store;
use App\Models\StoreCarPicture;
use App\Models\StoresCar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProviderStoreCarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('images.disk'));
    }

    public function test_provider_can_list_their_store_cars(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        StoresCar::factory()->create(['store_id' => $store->id]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/stores/{$store->id}/cars")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_provider_can_browse_cars_of_another_providers_store(): void
    {
        $provider = User::factory()->provider()->create();
        $otherStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        StoresCar::factory()->create(['store_id' => $otherStore->id]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/stores/{$otherStore->id}/cars")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_provider_can_create_car_for_their_store(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $carName = CarName::factory()->create();
        $color = Color::factory()->create();
        $fuel = FuelType::factory()->create();
        $section = CarSection::factory()->create();

        $payload = [
            'car_name_id' => $carName->id,
            'manufacturing_year' => 2020,
            'color_id' => $color->id,
            'fuel_type' => $fuel->id,
            'pictures' => [$this->image(), $this->image()],
            'sections' => [
                ['section_id' => $section->id, 'condition' => 'okay'],
            ],
        ];

        $this->actingAs($provider, 'sanctum')
            ->post("/api/provider/store/{$store->id}/cars", $payload, ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.manufacturing_year', 2020)
            ->assertJsonMissingPath('data.vehicle_plat_number');

        $this->assertDatabaseHas('stores_cars', [
            'store_id' => $store->id,
            'car_name_id' => $carName->id,
        ]);
        $this->assertFalse(Schema::hasColumn('stores_cars', 'vehicle_plat_number'));
        $this->assertSame(2, StoreCarPicture::where('car_id', StoresCar::where('store_id', $store->id)->first()->id)->count());

        $carId = StoresCar::where('store_id', $store->id)->first()->id;
        $this->assertDatabaseHas('store_car_sections', [
            'store_car_id' => $carId,
            'section_id' => $section->id,
            'condition' => 'okay',
        ]);
    }

    public function test_provider_cannot_create_car_for_another_store(): void
    {
        $provider = User::factory()->provider()->create();
        $otherStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $carName = CarName::factory()->create();
        $color = Color::factory()->create();
        $fuel = FuelType::factory()->create();
        $section = CarSection::factory()->create();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/store/{$otherStore->id}/cars", [
                'car_name_id' => $carName->id,
                'manufacturing_year' => 2020,
                'color_id' => $color->id,
                'fuel_type' => $fuel->id,
                'sections' => [
                    ['section_id' => $section->id, 'condition' => 'okay'],
                ],
            ])
            ->assertStatus(403);
    }

    public function test_create_car_requires_sections(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $carName = CarName::factory()->create();
        $color = Color::factory()->create();
        $fuel = FuelType::factory()->create();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/store/{$store->id}/cars", [
                'car_name_id' => $carName->id,
                'manufacturing_year' => 2020,
                'color_id' => $color->id,
                'fuel_type' => $fuel->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sections');
    }

    public function test_create_car_requires_valid_section_condition(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $carName = CarName::factory()->create();
        $color = Color::factory()->create();
        $fuel = FuelType::factory()->create();
        $section = CarSection::factory()->create();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/store/{$store->id}/cars", [
                'car_name_id' => $carName->id,
                'manufacturing_year' => 2020,
                'color_id' => $color->id,
                'fuel_type' => $fuel->id,
                'sections' => [
                    ['section_id' => $section->id, 'condition' => 'broken'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sections.0.condition');
    }

    public function test_create_car_requires_required_fields(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/store/{$store->id}/cars", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['car_name_id', 'manufacturing_year', 'color_id', 'fuel_type', 'sections']);
    }

    public function test_provider_can_view_a_store_car(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/stores/{$store->id}/cars/{$car->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $car->id);
    }

    public function test_cannot_view_car_of_different_store(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $otherStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $car = StoresCar::factory()->create(['store_id' => $otherStore->id]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/stores/{$store->id}/cars/{$car->id}")
            ->assertStatus(404);
    }

    public function test_provider_can_update_a_store_car(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);

        $this->actingAs($provider, 'sanctum')
            ->putJson("/api/provider/store/{$store->id}/cars/{$car->id}", [
                'manufacturing_year' => 2025,
            ])
            ->assertOk()
            ->assertJsonPath('data.manufacturing_year', 2025);

        $this->assertDatabaseHas('stores_cars', ['id' => $car->id, 'manufacturing_year' => 2025]);
    }

    public function test_update_replaces_pictures(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        StoreCarPicture::factory()->count(2)->create(['car_id' => $car->id]);

        $this->actingAs($provider, 'sanctum')
            ->post("/api/provider/store/{$store->id}/cars/{$car->id}", [
                '_method' => 'PUT',
                'pictures' => [$this->image()],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonCount(1, 'data.pictures')
            ->assertJsonPath('data.pictures.0.mime_type', 'image/png');

        $this->assertSame(1, StoreCarPicture::where('car_id', $car->id)->count());
    }

    public function test_provider_can_delete_a_store_car(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);

        $this->actingAs($provider, 'sanctum')
            ->deleteJson("/api/provider/store/{$store->id}/cars/{$car->id}")
            ->assertOk();

        $this->assertSoftDeleted('stores_cars', ['id' => $car->id]);
    }

    public function test_cannot_delete_car_of_different_store(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $otherStore = Store::factory()->create(['user_id' => User::factory()->provider()->create()->id]);
        $car = StoresCar::factory()->create(['store_id' => $otherStore->id]);

        $this->actingAs($provider, 'sanctum')
            ->deleteJson("/api/provider/store/{$store->id}/cars/{$car->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('stores_cars', ['id' => $car->id]);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('car.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg=='
        ));
    }
}
