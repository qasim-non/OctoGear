<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Events\OrderCreated;
use App\Models\Admin;
use App\Models\CarName;
use App\Models\Color;
use App\Models\Component;
use App\Models\CustomerCar;
use App\Models\FuelType;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GeneralOrderDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_vehicle_and_custom_part_require_no_garage_save_description_or_quantity(): void
    {
        Event::fake([OrderCreated::class]);
        $customer = $this->customer();
        $payload = $this->payload();
        $response = $this->postJson('/api/customer/orders', $payload)->assertCreated()
            ->assertJsonPath('data.component_name', 'All mirrors')
            ->assertJsonPath('data.component_id', null)
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.vehicle_details.transmission_type', 'automatic')
            ->assertJsonPath('data.vehicle_details.manufacturing_year', 2022)
            ->assertJsonPath('data.vehicle_details.car_name', 'Camry')
            ->assertJsonPath('data.vehicle_details.color_id', $payload['vehicle']['color_id'])
            ->assertJsonPath('data.vehicle_details.fuel_type', $payload['vehicle']['fuel_type'])
            ->assertJsonPath('data.vehicle_details.color_name', 'White')
            ->assertJsonPath('data.vehicle_details.fuel_type_name', 'Petrol')
            ->assertJsonPath('data.vehicle_details.customer_car_id', null)
            ->assertJsonMissingPath('data.quantity')->assertJsonMissingPath('data.car_model');
        $this->assertDatabaseCount('customer_cars', 0);
        $this->assertDatabaseCount('order_vehicle_details', 1);
        $this->assertDatabaseHas('orders', ['id' => $response->json('data.id'), 'customer_id' => $customer->id, 'quantity' => 1]);
        Event::assertDispatchedTimes(OrderCreated::class, 1);
    }

    public function test_manual_color_and_fuel_are_required_valid_active_references_in_both_languages(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->customer();
        $payload = [...$this->payload(), 'save_to_my_cars' => true];
        foreach (['en', 'ar'] as $locale) {
            $this->withHeader('Accept-Language', $locale);
            foreach (['color_id' => Color::class, 'fuel_type' => FuelType::class] as $field => $model) {
                $missing = $payload;
                unset($missing['vehicle'][$field]);
                $this->postJson('/api/customer/orders', $missing)->assertUnprocessable()->assertJsonValidationErrors('vehicle.'.$field);
                $retired = $model::factory()->create();
                $retired->delete();
                foreach ([null, 0, 'invalid', ['id' => 1], 999999, $retired->id] as $value) {
                    $input = $payload;
                    $input['vehicle'][$field] = $value;
                    $response = $this->postJson('/api/customer/orders', $input)->assertUnprocessable()
                        ->assertJsonPath('success', false)->assertJsonValidationErrors('vehicle.'.$field);
                    $this->assertStringNotContainsString('auth.validation.', $response->getContent());
                }
            }
        }
        foreach (['orders', 'customer_cars', 'order_vehicle_details'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_saved_car_with_unavailable_color_or_fuel_must_be_completed_before_requesting(): void
    {
        $customer = $this->customer();
        foreach (['color_id' => Color::class, 'fuel_type' => FuelType::class] as $field => $model) {
            $car = CustomerCar::factory()->create([
                'customer_id' => $customer->id, 'car_name_id' => CarName::factory(),
                'color_id' => Color::factory(), 'fuel_type' => FuelType::factory(),
            ]);
            $model::findOrFail($car->{$field})->delete();
            $payload = ['order_type' => 'general', 'customer_car_id' => $car->id, 'component_name' => 'Mirror'];
            $this->withHeader('Idempotency-Key', (string) Str::uuid());
            foreach (['en', 'ar'] as $locale) {
                $this->withHeader('Accept-Language', $locale)->postJson('/api/customer/orders', $payload)
                    ->assertUnprocessable()->assertJsonValidationErrors('customer_car_id')
                    ->assertJsonPath('errors.customer_car_id.0', __('auth.validation.general_order.complete_car', [], $locale));
            }
            $replacement = $model::factory()->create();
            $this->patchJson('/api/customer/customer-cars/'.$car->id, [$field => $replacement->id])->assertOk();
            $this->postJson('/api/customer/orders', $payload)->assertCreated()
                ->assertJsonPath('data.vehicle_details.'.$field, $replacement->id);
        }
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_color_and_fuel_snapshots_survive_garage_and_catalog_changes_and_deletion(): void
    {
        $this->customer();
        $payload = [...$this->payload(), 'save_to_my_cars' => true];
        $response = $this->postJson('/api/customer/orders', $payload)->assertCreated();
        $id = $response->json('data.id');
        $car = CustomerCar::findOrFail($response->json('data.vehicle_details.customer_car_id'));
        $color = Color::findOrFail($payload['vehicle']['color_id']);
        $fuel = FuelType::findOrFail($payload['vehicle']['fuel_type']);
        $car->update(['color_id' => Color::factory()->create()->id, 'fuel_type' => FuelType::factory()->create()->id]);
        $color->update(['name_en' => 'Changed', 'name_ar' => 'تغيير']);
        $fuel->update(['type_en' => 'Changed', 'type_ar' => 'تغيير']);
        $color->forceDelete();
        $fuel->forceDelete();
        foreach (['en' => ['White', 'Petrol'], 'ar' => ['أبيض', 'بنزين']] as $locale => [$colorName, $fuelName]) {
            $this->withHeader('Accept-Language', $locale)->getJson('/api/customer/orders/'.$id)->assertOk()
                ->assertJsonPath('data.vehicle_details.color_id', null)->assertJsonPath('data.vehicle_details.fuel_type', null)
                ->assertJsonPath('data.vehicle_details.color_name', $colorName)->assertJsonPath('data.vehicle_details.fuel_type_name', $fuelName);
        }
        $this->postJson('/api/customer/orders', $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('customer_cars', 1);
    }

    public function test_custom_part_names_are_stored_once_and_never_translated(): void
    {
        $this->customer();
        $payload = $this->payload();
        foreach (['مرايا السيارة', 'Rétroviseur gauche', 'ミラー'] as $name) {
            $this->withHeader('Idempotency-Key', (string) Str::uuid());
            $id = null;
            foreach (['ar', 'en'] as $locale) {
                $response = $this->withHeader('Accept-Language', $locale)
                    ->postJson('/api/customer/orders', [...$payload, 'component_name' => $name])->assertCreated()
                    ->assertJsonPath('data.component_name', $name)->assertJsonPath('data.component_id', null)
                    ->assertJsonMissingPath('data.component_name_ar');
                $id ??= $response->json('data.id');
                $this->assertSame($id, $response->json('data.id'));
            }
            $this->assertDatabaseHas('orders', ['id' => $id, 'component_name' => $name, 'component_id' => null]);
        }
        $this->assertDatabaseCount('orders', 3);
    }

    public function test_catalog_components_used_by_orders_can_be_soft_deleted_but_not_permanently_removed(): void
    {
        $this->customer();
        $payload = $this->payload();
        unset($payload['component_name']);
        $component = Component::factory()->create(['name_en' => 'Mirror', 'name_ar' => 'مرآة']);
        $id = $this->postJson('/api/customer/orders', [...$payload, 'component_id' => $component->id])->assertCreated()->json('data.id');
        $component->delete();
        try {
            $component->forceDelete();
            $this->fail('An order must keep its catalog reference.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0]);
        }
        $this->getJson('/api/customer/orders/'.$id)->assertOk()->assertJsonPath('data.component_name', 'Mirror');
    }

    public function test_vehicle_snapshot_stays_fixed_while_catalog_part_name_is_read_through_its_id(): void
    {
        $customer = $this->customer();
        $car = CustomerCar::factory()->create(['car_name_id' => CarName::factory(), 'color_id' => Color::factory(), 'fuel_type' => FuelType::factory(), 'customer_id' => $customer->id, 'manufacturing_year' => 2020, 'transmission_type' => 'manual']);
        $car->carName->update(['name_en' => 'Sunny', 'name_ar' => 'صني']);
        $part = Component::factory()->create(['name_en' => 'Mirror', 'name_ar' => 'مرآة']);
        $payload = ['order_type' => 'general', 'customer_car_id' => $car->id, 'component_id' => $part->id, 'description' => 'Left and right'];
        $id = $this->postJson('/api/customer/orders', $payload)->assertCreated()
            ->assertJsonPath('data.description', 'Left and right')->json('data.id');
        $this->assertNull(Order::findOrFail($id)->component_name);
        $car->update(['manufacturing_year' => 2025, 'transmission_type' => 'automatic']);
        $car->carName->update(['name_en' => 'Renamed']);
        $part->update(['name_en' => 'Changed']);
        $car->forceDelete();
        $part->delete();
        $car->carName->forceDelete();
        $this->getJson('/api/customer/orders/'.$id)->assertOk()
            ->assertJsonPath('data.part_name', 'Changed')->assertJsonPath('data.component_id', $part->id)
            ->assertJsonPath('data.car_name', 'Sunny')->assertJsonPath('data.manufacturing_year', 2020)
            ->assertJsonPath('data.vehicle_details.transmission_type', 'manual')
            ->assertJsonPath('data.vehicle_details.customer_car_id', null)
            ->assertJsonPath('data.vehicle_details.car_name_id', null);
        $this->withHeader('Accept-Language', 'ar')->getJson('/api/customer/orders/'.$id)->assertOk()
            ->assertJsonPath('data.part_name', 'مرآة')->assertJsonPath('data.car_name', 'صني');
        // Deleted dependencies do not invalidate a matching submission retry.
        $this->postJson('/api/customer/orders', $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_optional_garage_save_returns_a_usable_car_and_retries_do_not_duplicate_it(): void
    {
        $this->customer();
        $payload = [...$this->payload(), 'save_to_my_cars' => true];
        $first = $this->postJson('/api/customer/orders', $payload)->assertCreated();
        $carId = $first->json('data.vehicle_details.customer_car_id');
        $this->assertNotNull($carId);
        $this->getJson('/api/customer/customer-cars/'.$carId)->assertOk()
            ->assertJsonPath('data.transmission_type', 'automatic')
            ->assertJsonMissingPath('data.vehicle_plat_number')->assertJsonPath('data.color.id', $payload['vehicle']['color_id'])
            ->assertJsonPath('data.fuel_type.id', $payload['vehicle']['fuel_type'])->assertJsonPath('data.car_name.name', 'Camry');
        $this->getJson('/api/customer/customer-cars')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/customer/orders', $payload)->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('customer_cars', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/customer/orders', [
            'order_type' => 'general', 'customer_car_id' => $carId, 'component_name' => 'Bumper',
        ])->assertCreated()->assertJsonPath('data.vehicle_details.customer_car_id', $carId);
        $this->assertDatabaseCount('customer_cars', 1);
    }

    public function test_saved_car_ownership_and_deleted_or_invalid_references_are_rejected_without_partial_saves(): void
    {
        $customer = $this->customer();
        $foreignCar = CustomerCar::factory()->create(['car_name_id' => CarName::factory(), 'color_id' => Color::factory(), 'fuel_type' => FuelType::factory(), 'customer_id' => User::factory()->customer()]);
        $ownedCar = CustomerCar::factory()->create(['car_name_id' => CarName::factory(), 'color_id' => Color::factory(), 'fuel_type' => FuelType::factory(), 'customer_id' => $customer->id]);
        $ownedCar->delete();
        foreach ([$foreignCar->id, $ownedCar->id, 999999] as $id) {
            $this->postJson('/api/customer/orders', ['order_type' => 'general', 'customer_car_id' => $id, 'component_name' => 'Mirror'])
                ->assertUnprocessable()->assertJsonPath('success', false)->assertJsonValidationErrors('customer_car_id');
        }
        $payload = [...$this->payload(), 'save_to_my_cars' => true];
        unset($payload['component_name']);
        $part = Component::factory()->create();
        $part->delete();
        foreach ([999999, $part->id] as $id) {
            $this->postJson('/api/customer/orders', [...$payload, 'component_id' => $id])->assertUnprocessable()->assertJsonValidationErrors('component_id');
        }
        $part = Component::factory()->create();
        $part->section->delete();
        $this->postJson('/api/customer/orders', [...$payload, 'component_id' => $part->id])->assertUnprocessable()->assertJsonValidationErrors('component_id');
        $payload = $this->payload();
        CarName::findOrFail($payload['vehicle']['car_name_id'])->delete();
        $this->postJson('/api/customer/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('vehicle.car_name_id');
        $payload = $this->payload();
        CarName::findOrFail($payload['vehicle']['car_name_id'])->carCompany->delete();
        $this->postJson('/api/customer/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('vehicle.car_name_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_vehicle_details', 0);
        $this->assertDatabaseCount('customer_cars', 2);
    }

    public function test_input_choices_types_limits_and_obsolete_fields_are_validated_in_both_languages(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->customer();
        $payload = $this->payload();
        $cases = [
            ['quantity' => 2], ['model_id' => 1], ['notes' => 'old'], ['store_car_component_id' => 1],
            ['customer_car_id' => 1], ['component_id' => 1], ['component_name' => ''],
            ['component_name' => str_repeat('x', 256)], ['description' => str_repeat('x', 1001)],
            ['description' => ['text']], ['save_to_my_cars' => 'yes'], ['vehicle' => []],
            ['vehicle' => [...$payload['vehicle'], 'manufacturing_year' => 1969]],
            ['vehicle' => [...$payload['vehicle'], 'manufacturing_year' => (int) date('Y') + 1]],
            ['vehicle' => [...$payload['vehicle'], 'transmission_type' => 'cvt']],
            ['vehicle' => [...$payload['vehicle'], 'transmission_type' => ['automatic']]],
            ['vehicle' => [...$payload['vehicle'], 'customer_id' => 1]],
        ];
        foreach (['en', 'ar'] as $language) {
            $this->withHeader('Accept-Language', $language);
            foreach ($cases as $change) {
                $response = $this->postJson('/api/customer/orders', [...$payload, ...$change])->assertUnprocessable()->assertJsonPath('success', false);
                $this->assertStringNotContainsString('auth.validation.', $response->getContent());
                $this->assertStringNotContainsString('validation.', $response->getContent());
            }
            $this->postJson('/api/customer/orders', ['order_type' => 'general'])->assertUnprocessable()
                ->assertJsonValidationErrors(['customer_car_id', 'vehicle', 'component_id', 'component_name']);
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_missing_keys_conflicting_retries_and_customer_scoping_are_handled(): void
    {
        $firstCustomer = $this->customer();
        $payload = $this->payload();
        $this->withHeader('Idempotency-Key', '')->postJson('/api/customer/orders', [...$payload, 'idempotency_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $key = (string) Str::uuid();
        $id = $this->withHeader('Idempotency-Key', $key)->postJson('/api/customer/orders', $payload)->assertCreated()->json('data.id');
        foreach ([['component_name' => 'Bumper'], ['save_to_my_cars' => true], ['description' => 'Changed'],
            ['vehicle' => [...$payload['vehicle'], 'color_id' => Color::factory()->create()->id]],
            ['vehicle' => [...$payload['vehicle'], 'fuel_type' => FuelType::factory()->create()->id]],
            ['vehicle' => [...$payload['vehicle'], 'transmission_type' => 'manual']]] as $change) {
            $this->postJson('/api/customer/orders', [...$payload, ...$change])->assertConflict();
        }
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')
            ->postJson('/api/customer/orders', $payload)->assertCreated();
        $this->assertDatabaseCount('orders', 2);
        $this->actingAs($firstCustomer, 'sanctum');
        Order::findOrFail($id)->delete();
        $this->postJson('/api/customer/orders', $payload)->assertConflict();
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_failed_image_write_rolls_back_the_garage_car_snapshot_order_file_and_notification(): void
    {
        Event::fake([OrderCreated::class]);
        config(['images.disk' => 'images_local']);
        Storage::fake('images_local');
        $this->customer();
        $payload = [...$this->payload(), 'save_to_my_cars' => true,
            'images' => [UploadedFile::fake()->createWithContent('mirror.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg=='))]];
        DB::unprepared("CREATE TRIGGER fail_general_image BEFORE INSERT ON order_images BEGIN SELECT RAISE(ABORT, 'Simulated failure'); END;");
        try {
            $this->post('/api/customer/orders', $payload)->assertStatus(500);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_general_image');
        }
        foreach (['orders', 'order_vehicle_details', 'customer_cars'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame([], Storage::disk('images_local')->allFiles());
        Event::assertNotDispatched(OrderCreated::class);
        $this->post('/api/customer/orders', $payload)->assertCreated();
        Event::assertDispatchedTimes(OrderCreated::class, 1);
    }

    public function test_provider_and_admin_read_the_snapshot_without_leaking_the_private_garage_link(): void
    {
        $customer = $this->customer();
        $car = CustomerCar::factory()->create(['car_name_id' => CarName::factory(), 'color_id' => Color::factory(), 'fuel_type' => FuelType::factory(), 'customer_id' => $customer->id]);
        $id = $this->postJson('/api/customer/orders', ['order_type' => 'general', 'customer_car_id' => $car->id, 'component_name' => 'All mirrors'])
            ->assertCreated()->assertJsonPath('data.vehicle_details.transmission_type', null)->json('data.id');
        $store = Store::factory()->create(['city_id' => $customer->city_id]);
        $this->actingAs($store->owner, 'sanctum')->getJson('/api/provider/orders/'.$id)->assertOk()
            ->assertJsonPath('data.component_name', 'All mirrors')
            ->assertJsonPath('data.vehicle_details.color_id', $car->color_id)
            ->assertJsonPath('data.vehicle_details.fuel_type', $car->fuel_type)
            ->assertJsonMissingPath('data.vehicle_details.customer_car_id')->assertJsonMissingPath('data.quantity');
        $this->getJson('/api/provider/orders/general')->assertOk()->assertJsonPath('data.0.id', $id)
            ->assertJsonMissingPath('data.0.vehicle_details.customer_car_id');
        $this->actingAs(Admin::factory()->create(), 'sanctum')->getJson('/api/admin/orders/'.$id)->assertOk()
            ->assertJsonPath('data.vehicle_details.color_id', $car->color_id)
            ->assertJsonPath('data.vehicle_details.fuel_type', $car->fuel_type)
            ->assertJsonPath('data.vehicle_details.customer_car_id', $car->id)->assertJsonMissingPath('data.quantity');
        $this->getJson('/api/admin/orders')->assertOk()->assertJsonPath('data.0.vehicle_details.customer_car_id', $car->id);
    }

    public function test_real_general_notifications_reach_only_active_local_stores_once_after_retry(): void
    {
        $customer = $this->customer();
        $local = Store::factory()->create(['city_id' => $customer->city_id]);
        $otherCity = Store::factory()->create();
        $inactive = Store::factory()->create(['city_id' => $customer->city_id, 'status' => StoreStatus::Inactive]);
        $payload = $this->payload();
        $id = $this->postJson('/api/customer/orders', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/customer/orders', $payload)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertSame(1, $local->owner->notifications()->count());
        $this->assertSame(0, $otherCity->owner->notifications()->count());
        $this->assertSame(0, $inactive->owner->notifications()->count());
        $this->assertDatabaseCount('notifications', 1);
    }

    private function customer(): User
    {
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer, 'sanctum')->withHeader('Accept-Language', 'en')->withHeader('Idempotency-Key', (string) Str::uuid());

        return $customer;
    }

    public function test_new_general_request_uses_the_whole_offer_total_through_the_existing_payment_stub(): void
    {
        config(['payments.driver' => 'stub']);
        $this->customer();
        $id = $this->postJson('/api/customer/orders', $this->payload())->assertCreated()->json('data.id');
        $store = Store::factory()->create();
        $offer = OrderOffer::factory()->create(['order_id' => $id, 'store_id' => $store->id, 'price' => 30000]);
        $this->postJson('/api/customer/orders/'.$id.'/accept-offer', ['offer_id' => $offer->id])->assertOk();
        $this->postJson('/api/customer/orders/'.$id.'/pay', ['payment_method' => 'credit_card', 'card_token' => 'test-token'])
            ->assertOk()->assertJsonPath('data.payment.amount', 30000);
        $this->actingAs($store->owner, 'sanctum')->getJson('/api/provider/orders/paid')->assertOk()
            ->assertJsonPath('data.0.gross_amount', 30000)
            ->assertJsonPath('data.0.component_name', 'All mirrors')
            ->assertJsonPath('data.0.vehicle_details.car_name', 'Camry')
            ->assertJsonMissingPath('data.0.quantity')->assertJsonMissingPath('data.0.vehicle_details.customer_car_id');
    }

    public function test_demo_seed_creates_general_snapshots_and_preserves_them_on_rerun(): void
    {
        config(['images.disk' => 'images_local', 'customer_car_media.disk' => 'images_local']);
        Storage::fake('images_local');
        $this->seed(DemoDataSeeder::class);
        $this->assertSame(30, Order::where('order_type', 'general')->count());
        $this->assertDatabaseCount('order_vehicle_details', 30);
        $order = Order::where('order_type', 'general')->firstOrFail();
        $this->assertNotNull($order->requestedComponentName('en'));
        $this->assertSame(15, Order::where('order_type', 'general')->whereNotNull('component_id')->whereNull('component_name')->count());
        $this->assertSame(15, Order::where('order_type', 'general')->whereNull('component_id')->whereNotNull('component_name')->count());
        $this->assertNotNull($order->vehicleDetails->car_name_en);
        $this->assertNotNull($order->vehicleDetails->color_id);
        $this->assertNotNull($order->vehicleDetails->fuel_type);
        $order->vehicleDetails->update(['car_name_en' => 'Preserve original snapshot']);
        $this->seed(DemoDataSeeder::class);
        $this->assertDatabaseCount('orders', 60);
        $this->assertDatabaseCount('order_vehicle_details', 30);
        $this->assertSame('Preserve original snapshot', $order->fresh()->vehicleDetails->car_name_en);
    }

    private function payload(): array
    {
        return ['order_type' => 'general', 'component_name' => 'All mirrors', 'vehicle' => [
            'car_name_id' => CarName::factory()->create(['name_en' => 'Camry', 'name_ar' => 'كامري'])->id,
            'manufacturing_year' => 2022, 'transmission_type' => 'automatic',
            'color_id' => Color::factory()->create(['name_en' => 'White', 'name_ar' => 'أبيض'])->id,
            'fuel_type' => FuelType::factory()->create(['type_en' => 'Petrol', 'type_ar' => 'بنزين'])->id,
        ]];
    }
}
