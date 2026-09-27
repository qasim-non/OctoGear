<?php

namespace Tests\Feature;

use App\Models\CarCompany;
use App\Models\CarName;
use App\Models\Color;
use App\Models\CustomerCar;
use App\Models\CustomerCarPicture;
use App\Models\FuelType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerCarManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['customer_car_media.disk' => 'customer_car_media_local']);
        Storage::fake('customer_car_media_local');
    }

    public function test_detail_includes_the_localized_company_and_ordered_private_picture_metadata(): void
    {
        $customer = User::factory()->customer()->create();
        $vehicle = $this->vehicle(
            companyEn: 'Toyota',
            companyAr: 'تويوتا',
            nameEn: 'Camry',
            nameAr: 'كامري',
        );
        $car = $this->car($customer, $vehicle);
        $firstPicture = $this->picture($car, 'customer-cars/'.$car->id.'/first.png', 0);
        $secondPicture = $this->picture($car, 'customer-cars/'.$car->id.'/second.png', 1);

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Accept-Language', 'en')
            ->getJson('/api/customer/customer-cars/'.$car->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company.id', $vehicle['company']->id)
            ->assertJsonPath('data.company.name', 'Toyota')
            ->assertJsonPath('data.car_name.name', 'Camry')
            ->assertJsonCount(2, 'data.pictures')
            ->assertJsonPath('data.pictures.0.id', $firstPicture->id)
            ->assertJsonPath(
                'data.pictures.0.url',
                '/api/customer/customer-cars/'.$car->id.'/pictures/'.$firstPicture->id,
            )
            ->assertJsonPath('data.pictures.1.id', $secondPicture->id);

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Accept-Language', 'ar')
            ->getJson('/api/customer/customer-cars/'.$car->id)
            ->assertOk()
            ->assertJsonPath('data.company.name', 'تويوتا')
            ->assertJsonPath('data.car_name.name', 'كامري');
    }

    public function test_historical_car_keeps_localized_name_and_company_after_catalog_retirement(): void
    {
        $customer = User::factory()->customer()->create();
        $vehicle = $this->vehicle(
            companyEn: 'Ford',
            companyAr: 'فورد',
            nameEn: 'Explorer',
            nameAr: 'إكسبلورر',
        );
        $car = $this->car($customer, $vehicle);

        $vehicle['name']->delete();
        $vehicle['company']->delete();

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Accept-Language', 'en')
            ->getJson('/api/customer/customer-cars/'.$car->id)
            ->assertOk()
            ->assertJsonPath('data.car_name.id', $vehicle['name']->id)
            ->assertJsonPath('data.car_name.name', 'Explorer')
            ->assertJsonPath('data.company.id', $vehicle['company']->id)
            ->assertJsonPath('data.company.name', 'Ford');

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Accept-Language', 'ar')
            ->getJson('/api/customer/customer-cars')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.car_name.name', 'إكسبلورر')
            ->assertJsonPath('data.0.company.name', 'فورد');
    }

    public function test_owner_can_update_and_soft_remove_a_car_while_private_media_is_retained(): void
    {
        $customer = User::factory()->customer()->create();
        $originalVehicle = $this->vehicle(companyEn: 'Toyota', companyAr: 'تويوتا');
        $replacementVehicle = $this->vehicle(companyEn: 'Honda', companyAr: 'هوندا');
        $car = $this->car($customer, $originalVehicle);
        $picture = $this->picture($car, 'customer-cars/'.$car->id.'/retained.png', 0);

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Accept-Language', 'en')
            ->patchJson('/api/customer/customer-cars/'.$car->id, [
                'car_name_id' => $replacementVehicle['name']->id,
                'manufacturing_year' => 2024,
                'vehicle_plat_number' => 'HON-2024',
                'color_id' => $replacementVehicle['color']->id,
                'fuel_type' => $replacementVehicle['fuel']->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.company.name', 'Honda')
            ->assertJsonPath('data.manufacturing_year', 2024)
            ->assertJsonPath('data.vehicle_plat_number', 'HON-2024');

        $this->assertDatabaseHas('customer_cars', [
            'id' => $car->id,
            'car_name_id' => $replacementVehicle['name']->id,
            'manufacturing_year' => 2024,
            'vehicle_plat_number' => 'HON-2024',
            'color_id' => $replacementVehicle['color']->id,
            'fuel_type' => $replacementVehicle['fuel']->id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->deleteJson('/api/customer/customer-cars/'.$car->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);

        $this->assertSoftDeleted('customer_cars', ['id' => $car->id]);
        $this->assertDatabaseHas('customer_car_pictures', ['id' => $picture->id]);
        Storage::disk('customer_car_media_local')->assertExists($picture->path);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/customer/customer-cars')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/customer/customer-cars/'.$car->id)
            ->assertNotFound();
    }

    public function test_another_customer_cannot_read_update_or_remove_a_car(): void
    {
        $owner = User::factory()->customer()->create();
        $otherCustomer = User::factory()->customer()->create();
        $car = $this->car($owner, $this->vehicle());

        $this->actingAs($otherCustomer, 'sanctum')
            ->getJson('/api/customer/customer-cars/'.$car->id)
            ->assertForbidden();

        $this->actingAs($otherCustomer, 'sanctum')
            ->patchJson('/api/customer/customer-cars/'.$car->id, [
                'manufacturing_year' => 2024,
            ])
            ->assertForbidden();

        $this->actingAs($otherCustomer, 'sanctum')
            ->deleteJson('/api/customer/customer-cars/'.$car->id)
            ->assertForbidden();

        $this->assertDatabaseHas('customer_cars', [
            'id' => $car->id,
            'manufacturing_year' => $car->manufacturing_year,
            'deleted_at' => null,
        ]);
    }

    public function test_soft_deleted_reference_values_are_rejected_for_create_and_update(): void
    {
        $customer = User::factory()->customer()->create();
        $deletedName = CarName::factory()->create();
        $deletedColor = Color::factory()->create();
        $deletedFuel = FuelType::factory()->create();
        $deletedName->delete();
        $deletedColor->delete();
        $deletedFuel->delete();

        $invalidPayload = [
            'car_name_id' => $deletedName->id,
            'manufacturing_year' => 2022,
            'vehicle_plat_number' => 'OLD-422',
            'color_id' => $deletedColor->id,
            'fuel_type' => $deletedFuel->id,
        ];

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/customer/customer-cars', $invalidPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['car_name_id', 'color_id', 'fuel_type']);

        $car = $this->car($customer, $this->vehicle());

        $this->actingAs($customer, 'sanctum')
            ->patchJson('/api/customer/customer-cars/'.$car->id, $invalidPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['car_name_id', 'color_id', 'fuel_type']);
    }

    /**
     * @return array{company: CarCompany, name: CarName, color: Color, fuel: FuelType}
     */
    private function vehicle(
        string $companyEn = 'Toyota',
        string $companyAr = 'تويوتا',
        string $nameEn = 'Camry',
        string $nameAr = 'كامري',
    ): array {
        $company = CarCompany::factory()->create([
            'name_en' => $companyEn,
            'name_ar' => $companyAr,
        ]);
        $name = CarName::factory()->create([
            'car_company_id' => $company->id,
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
        ]);

        return [
            'company' => $company,
            'name' => $name,
            'color' => Color::factory()->create(),
            'fuel' => FuelType::factory()->create(),
        ];
    }

    /**
     * @param  array{company: CarCompany, name: CarName, color: Color, fuel: FuelType}  $vehicle
     */
    private function car(User $customer, array $vehicle): CustomerCar
    {
        return CustomerCar::factory()->create([
            'customer_id' => $customer->id,
            'car_name_id' => $vehicle['name']->id,
            'color_id' => $vehicle['color']->id,
            'fuel_type' => $vehicle['fuel']->id,
            'manufacturing_year' => 2022,
            'vehicle_plat_number' => 'CAR-2022',
        ]);
    }

    private function picture(CustomerCar $car, string $path, int $sortOrder): CustomerCarPicture
    {
        Storage::disk('customer_car_media_local')->put($path, 'private image');

        return CustomerCarPicture::query()->create([
            'car_id' => $car->id,
            'disk' => 'customer_car_media_local',
            'path' => $path,
            'mime_type' => 'image/png',
            'size_bytes' => 13,
            'sort_order' => $sortOrder,
        ]);
    }
}
