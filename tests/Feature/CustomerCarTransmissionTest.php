<?php

namespace Tests\Feature;

use App\Enums\TransmissionType;
use App\Models\CarName;
use App\Models\Color;
use App\Models\CustomerCar;
use App\Models\FuelType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerCarTransmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_transmission_round_trips_through_create_detail_list_and_model(): void
    {
        $customer = User::factory()->customer()->create();
        $payload = $this->payload();

        foreach (TransmissionType::cases() as $type) {
            $response = $this->actingAs($customer, 'sanctum')
                ->withHeader('Idempotency-Key', (string) Str::uuid())
                ->postJson('/api/customer/customer-cars', [...$payload, 'transmission_type' => $type->value])
                ->assertCreated()
                ->assertJsonPath('data.transmission_type', $type->value);
            $id = $response->json('data.id');

            $this->assertSame($type, CustomerCar::findOrFail($id)->transmission_type);
            $this->assertDatabaseHas('customer_cars', ['id' => $id, 'transmission_type' => $type->value]);
            foreach (['ar', 'en'] as $locale) {
                $this->withHeader('Accept-Language', $locale)
                    ->getJson('/api/customer/customer-cars/'.$id)
                    ->assertOk()->assertJsonPath('data.transmission_type', $type->value);
            }
        }

        $cars = $this->getJson('/api/customer/customer-cars')->assertOk()->assertJsonCount(3, 'data')->json('data');
        $this->assertEqualsCanonicalizing(['automatic', 'manual', 'unknown'], array_column($cars, 'transmission_type'));
    }

    public function test_old_create_payloads_and_explicit_null_remain_supported(): void
    {
        $customer = User::factory()->customer()->create();
        $payload = $this->payload();
        foreach ([$payload, [...$payload, 'transmission_type' => null]] as $input) {
            $this->actingAs($customer, 'sanctum')
                ->withHeader('Idempotency-Key', (string) Str::uuid())
                ->postJson('/api/customer/customer-cars', $input)
                ->assertCreated()->assertJsonPath('data.transmission_type', null);
        }
        $this->assertSame(2, CustomerCar::whereNull('transmission_type')->count());
    }

    public function test_updates_preserve_omitted_values_and_allow_unknown_or_explicit_clearing(): void
    {
        $customer = User::factory()->customer()->create();
        $car = CustomerCar::factory()->create([
            ...$this->payload(), 'customer_id' => $customer->id, 'transmission_type' => 'automatic',
        ]);
        $this->actingAs($customer, 'sanctum')
            ->patchJson('/api/customer/customer-cars/'.$car->id, ['manufacturing_year' => 2024])
            ->assertOk()->assertJsonPath('data.transmission_type', 'automatic');

        foreach (['manual', 'unknown', null] as $value) {
            $this->patchJson('/api/customer/customer-cars/'.$car->id, ['transmission_type' => $value])
                ->assertOk()->assertJsonPath('data.transmission_type', $value);
            $this->assertDatabaseHas('customer_cars', ['id' => $car->id, 'transmission_type' => $value]);
        }
        $this->assertSame(2024, $car->fresh()->manufacturing_year);
    }

    public function test_invalid_values_are_rejected_on_create_and_update_with_localized_errors(): void
    {
        $customer = User::factory()->customer()->create();
        $payload = $this->payload();
        $car = CustomerCar::factory()->create([
            ...$payload, 'customer_id' => $customer->id, 'transmission_type' => 'manual',
        ]);

        foreach (['ar', 'en'] as $locale) {
            $this->actingAs($customer, 'sanctum')->withHeader('Accept-Language', $locale);
            foreach (['cvt', 'AUTOMATIC', 1, true, ['manual'], ['value' => 'manual']] as $invalid) {
                $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())
                    ->postJson('/api/customer/customer-cars', [...$payload, 'transmission_type' => $invalid])
                    ->assertUnprocessable()->assertJsonValidationErrors('transmission_type');
                $this->assertContains(
                    trans('auth.validation.transmission_type.invalid', [], $locale),
                    $response->json('errors.transmission_type'),
                );
                $this->patchJson('/api/customer/customer-cars/'.$car->id, ['transmission_type' => $invalid])
                    ->assertUnprocessable()->assertJsonValidationErrors('transmission_type');
            }
        }
        $this->assertSame(1, CustomerCar::count());
        $this->assertSame(TransmissionType::Manual, $car->fresh()->transmission_type);
    }

    public function test_retries_replay_the_same_selection_and_reject_a_changed_selection(): void
    {
        $customer = User::factory()->customer()->create();
        $payload = [...$this->payload(), 'transmission_type' => 'automatic'];
        $this->actingAs($customer, 'sanctum')->withHeader('Idempotency-Key', (string) Str::uuid());
        $id = $this->postJson('/api/customer/customer-cars', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/customer/customer-cars', $payload)->assertCreated()->assertJsonPath('data.id', $id);

        foreach (['manual', 'unknown', null] as $value) {
            $this->postJson('/api/customer/customer-cars', [...$payload, 'transmission_type' => $value])->assertConflict();
        }
        unset($payload['transmission_type']);
        $this->postJson('/api/customer/customer-cars', $payload)->assertConflict();
        $this->assertSame(1, CustomerCar::count());
        $this->assertSame(TransmissionType::Automatic, CustomerCar::findOrFail($id)->transmission_type);
    }

    public function test_pre_upgrade_fingerprints_still_replay_for_old_clients(): void
    {
        $customer = User::factory()->customer()->create();
        $payload = $this->payload();
        $key = (string) Str::uuid();
        // Exact persisted pre-upgrade format, deliberately without transmission.
        $fingerprint = hash('sha256', json_encode([
            'car_name_id' => $payload['car_name_id'],
            'manufacturing_year' => $payload['manufacturing_year'],
            'color_id' => $payload['color_id'],
            'fuel_type' => $payload['fuel_type'],
            'pictures' => [],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $car = CustomerCar::factory()->create([
            ...$payload, 'customer_id' => $customer->id,
            'idempotency_key' => $key, 'idempotency_fingerprint' => $fingerprint,
        ]);
        $this->actingAs($customer, 'sanctum')->withHeader('Idempotency-Key', $key);
        foreach ([$payload, [...$payload, 'transmission_type' => null]] as $input) {
            $this->postJson('/api/customer/customer-cars', $input)
                ->assertCreated()->assertJsonPath('data.id', $car->id)->assertJsonPath('data.transmission_type', null);
        }
        $this->postJson('/api/customer/customer-cars', [...$payload, 'transmission_type' => 'unknown'])->assertConflict();
        $this->assertSame(1, CustomerCar::count());
    }

    public function test_non_owners_and_providers_cannot_change_transmission(): void
    {
        $owner = User::factory()->customer()->create();
        $car = CustomerCar::factory()->create([
            ...$this->payload(), 'customer_id' => $owner->id, 'transmission_type' => 'manual',
        ]);
        $this->patchJson('/api/customer/customer-cars/'.$car->id, ['transmission_type' => 'automatic'])->assertUnauthorized();
        foreach ([User::factory()->customer()->create(), User::factory()->provider()->create()] as $other) {
            $this->actingAs($other, 'sanctum')
                ->patchJson('/api/customer/customer-cars/'.$car->id, ['transmission_type' => 'automatic'])
                ->assertForbidden();
        }
        $this->assertSame(TransmissionType::Manual, $car->fresh()->transmission_type);
    }

    public function test_additive_migration_preserves_existing_cars_without_guessing_transmission(): void
    {
        // Exercise down/up only inside the isolated test database.
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $migration = require database_path('migrations/2026_10_01_000001_add_transmission_type_to_customer_cars.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('customer_cars', 'transmission_type'));
        $car = CustomerCar::factory()->create([
            ...$this->payload(), 'customer_id' => User::factory()->customer()->create()->id,
        ]);
        $before = (array) DB::table('customer_cars')->find($car->id);
        $migration->up();
        $this->assertSame([...$before, 'transmission_type' => null], (array) DB::table('customer_cars')->find($car->id));
        $this->actingAs($car->customer, 'sanctum')
            ->getJson('/api/customer/customer-cars/'.$car->id)->assertOk()->assertJsonPath('data.transmission_type', null);
    }

    private function payload(): array
    {
        return [
            'car_name_id' => CarName::factory()->create()->id,
            'manufacturing_year' => 2022,
            'color_id' => Color::factory()->create()->id,
            'fuel_type' => FuelType::factory()->create()->id,
        ];
    }
}
