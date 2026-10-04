<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CarName;
use App\Models\Color;
use App\Models\Component;
use App\Models\CustomerCar;
use App\Models\FuelType;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\User;
use App\Services\CustomerOrderManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Event::fake();
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')->withHeader('Accept-Language', 'en');
    }

    private function order(array $attributes = []): Order
    {
        return Order::factory()->create(['customer_id' => auth()->id(), ...$attributes]);
    }

    private function edit(Order $order, array $data = [])
    {
        return $this->patchJson('/api/customer/orders/'.$order->id, ['edit_token' => CustomerOrderManagement::token($order->fresh()), ...$data]);
    }

    private function remove(Order $order)
    {
        return $this->deleteJson('/api/customer/orders/'.$order->id, ['edit_token' => CustomerOrderManagement::token($order->fresh())]);
    }

    public function test_owner_can_correct_part_and_vehicle_and_old_token_cannot_overwrite_changes(): void
    {
        $order = $this->order(['component_name' => 'Mirror']);
        $token = CustomerOrderManagement::token($order);
        $component = Component::factory()->create();
        $vehicle = ['car_name_id' => CarName::factory()->create()->id, 'manufacturing_year' => 2022,
            'transmission_type' => 'automatic', 'color_id' => Color::factory()->create()->id,
            'fuel_type' => FuelType::factory()->create()->id];
        $this->edit($order, ['component_id' => $component->id, 'vehicle' => $vehicle, 'description' => 'Left side'])
            ->assertOk()->assertJsonPath('data.can_edit', true)->assertJsonPath('data.component_id', $component->id)
            ->assertJsonPath('data.vehicle_details.manufacturing_year', 2022);
        $this->assertNull($order->fresh()->component_name);
        $this->patchJson('/api/customer/orders/'.$order->id, ['edit_token' => $token, 'description' => 'Stale'])
            ->assertConflict();
        $this->edit($order, ['component_name' => 'Custom mirror', 'description' => null])->assertOk();
        $this->assertNull($order->fresh()->component_id);
        $this->assertNull($order->fresh()->notes);
        $this->assertDatabaseCount('order_vehicle_details', 1);
        $this->assertDatabaseCount('customer_cars', 0);
    }

    public function test_invalid_vehicle_rolls_back_entire_edit(): void
    {
        $order = $this->order(['notes' => 'Original']);
        $this->edit($order, ['description' => 'Changed', 'vehicle' => [
            'car_name_id' => 999999, 'manufacturing_year' => 2022,
            'transmission_type' => 'automatic', 'color_id' => Color::factory()->create()->id,
            'fuel_type' => FuelType::factory()->create()->id,
        ]])->assertUnprocessable()->assertJsonValidationErrors('vehicle.car_name_id');
        $this->assertSame('Original', $order->fresh()->notes);
    }

    public function test_saved_car_is_supported_on_creation_but_rejected_on_update(): void
    {
        $car = $this->savedCar();
        $otherCar = $this->savedCar();
        $foreignCar = $this->savedCar(['customer_id' => User::factory()->customer()->create()->id]);
        $order = $this->createFromCar($car);
        $snapshot = $order->vehicleDetails->getAttributes();
        $token = CustomerOrderManagement::token($order);

        foreach ([$car->id, $otherCar->id, $foreignCar->id, 999999] as $carId) {
            foreach (['en', 'ar'] as $locale) {
                $this->withHeader('Accept-Language', $locale);
                $this->edit($order, ['customer_car_id' => $carId, 'description' => 'Must not persist'])
                    ->assertUnprocessable()->assertJsonValidationErrors('customer_car_id');
                $this->assertSame('Original', $order->fresh()->notes);
                $this->assertSame($snapshot, $order->fresh()->vehicleDetails->getAttributes());
                $this->assertSame($token, CustomerOrderManagement::token($order->fresh()));
            }
        }

        $this->edit($order, ['customer_car_id' => $otherCar->id, 'vehicle' => $this->vehicle()])
            ->assertUnprocessable()->assertJsonValidationErrors('customer_car_id');
        $this->assertSame($snapshot, $order->fresh()->vehicleDetails->getAttributes());
        $this->assertDatabaseCount('order_vehicle_details', 1);
    }

    public function test_vehicle_omission_retains_saved_car_snapshot_and_manual_edit_does_not_change_garage(): void
    {
        $car = $this->savedCar();
        $garage = $car->fresh()->getAttributes();
        $order = $this->createFromCar($car);
        $snapshot = $order->vehicleDetails->getAttributes();

        $this->edit($order, ['description' => 'Notes only'])->assertOk();
        $this->assertSame($snapshot, $order->fresh()->vehicleDetails->getAttributes());

        $vehicle = $this->vehicle();
        $this->edit($order, ['vehicle' => $vehicle, 'save_to_my_cars' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('save_to_my_cars');
        $this->edit($order, ['vehicle' => [...$vehicle, 'customer_car_id' => $car->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('vehicle');
        $this->edit($order, ['vehicle' => ['car_name_id' => $vehicle['car_name_id']]])
            ->assertUnprocessable()->assertJsonValidationErrors('vehicle.manufacturing_year');
        $this->assertSame($snapshot, $order->fresh()->vehicleDetails->getAttributes());

        $this->edit($order, ['vehicle' => $vehicle])->assertOk()
            ->assertJsonPath('data.vehicle_details.customer_car_id', null)
            ->assertJsonPath('data.vehicle_details.car_name_id', $vehicle['car_name_id'])
            ->assertJsonPath('data.vehicle_details.manufacturing_year', 2023);
        $this->assertSame($garage, $car->fresh()->getAttributes());
        $this->assertDatabaseCount('customer_cars', 1);
        $this->assertDatabaseCount('order_vehicle_details', 1);
    }

    private function savedCar(array $attributes = []): CustomerCar
    {
        return CustomerCar::factory()->create([
            'customer_id' => auth()->id(), 'car_name_id' => CarName::factory()->create()->id,
            'color_id' => Color::factory()->create()->id, 'fuel_type' => FuelType::factory()->create()->id,
            'manufacturing_year' => 2020, 'transmission_type' => 'manual', ...$attributes,
        ]);
    }

    private function createFromCar(CustomerCar $car): Order
    {
        $id = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/customer/orders', [
                'order_type' => 'general', 'customer_car_id' => $car->id,
                'component_name' => 'Mirror', 'description' => 'Original',
            ])->assertCreated()->assertJsonPath('data.vehicle_details.customer_car_id', $car->id)
            ->json('data.id');

        return Order::with('vehicleDetails')->findOrFail($id);
    }

    private function vehicle(): array
    {
        return [
            'car_name_id' => CarName::factory()->create()->id, 'manufacturing_year' => 2023,
            'transmission_type' => 'automatic', 'color_id' => Color::factory()->create()->id,
            'fuel_type' => FuelType::factory()->create()->id,
        ];
    }

    public function test_other_customer_cannot_edit_or_delete(): void
    {
        $order = $this->order(['customer_id' => User::factory()->customer()->create()->id]);
        $this->edit($order, ['description' => 'Changed'])->assertForbidden();
        $this->remove($order)->assertForbidden();
        $this->assertNotSoftDeleted($order);
    }

    public function test_offers_including_withdrawn_offers_lock_editing_but_pending_request_can_be_deleted(): void
    {
        $order = $this->order();
        $offer = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => Store::factory()->create()->id]);
        $this->edit($order, ['description' => 'Changed'])->assertConflict();
        $offer->delete();
        $this->edit($order, ['description' => 'Changed'])->assertConflict();
        $this->getJson('/api/customer/orders/'.$order->id)->assertOk()->assertJsonPath('data.can_edit', false)->assertJsonPath('data.can_delete', true);
        $offer->restore();
        $this->remove($order)->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertSoftDeleted($order);
        $this->assertSoftDeleted($offer);
        $this->getJson('/api/customer/orders/'.$order->id)->assertNotFound();
        $this->getJson('/api/customer/orders')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_accepted_paid_completed_orders_and_any_payment_record_are_protected(): void
    {
        foreach ([OrderStatus::AwaitingPayment, OrderStatus::Paid, OrderStatus::Completed] as $status) {
            $order = $this->order(['status' => $status]);
            $this->edit($order, ['description' => 'Changed'])->assertConflict();
            $this->remove($order)->assertConflict();
        }
        $order = $this->order();
        Payment::factory()->create(['order_id' => $order->id]);
        $this->edit($order, ['description' => 'Changed'])->assertConflict();
        $this->remove($order)->assertConflict();
    }

    public function test_specific_quantity_checks_stock_and_keeps_original_unit_price(): void
    {
        $part = StoreCarComponent::factory()->create(['stock_quantity' => 3]);
        $order = $this->order(['order_type' => 'specific', 'store_car_component_id' => $part->id, 'requested_unit_price' => 1250]);
        $this->edit($order, ['quantity' => 2, 'notes' => 'Please pack carefully'])->assertOk()
            ->assertJsonPath('data.quantity', 2)->assertJsonPath('data.requested_unit_price', 1250);
        $this->edit($order, ['quantity' => 4])->assertUnprocessable();
        $this->edit($order, ['component_name' => 'Different part'])->assertUnprocessable();
        $this->edit($order, ['status' => 'paid'])->assertUnprocessable();
        $this->assertSame(2, $order->fresh()->quantity);
    }

    public function test_deleted_request_cannot_be_resurrected_by_create_retry(): void
    {
        $key = (string) Str::uuid();
        $order = $this->order(['idempotency_key' => $key, 'idempotency_fingerprint' => str_repeat('a', 64)]);
        $this->remove($order)->assertOk();
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/customer/orders', [
            'order_type' => 'general', 'customer_car_id' => 1, 'component_name' => 'Mirror',
        ])->assertConflict();
    }

    public function test_response_token_works_across_languages_and_invalid_snapshot_edit_rolls_back(): void
    {
        $order = $this->order();
        $vehicle = ['car_name_id' => CarName::factory()->create()->id, 'manufacturing_year' => 2022,
            'transmission_type' => 'automatic', 'color_id' => Color::factory()->create()->id,
            'fuel_type' => FuelType::factory()->create()->id];
        $this->edit($order, ['vehicle' => $vehicle])->assertOk();
        $snapshot = $order->fresh()->vehicleDetails->getAttributes();
        $token = $this->getJson('/api/customer/orders/'.$order->id)->assertOk()->json('data.edit_token');
        $this->withHeader('Accept-Language', 'ar')->patchJson('/api/customer/orders/'.$order->id, [
            'edit_token' => $token, 'description' => 'تفاصيل جديدة',
        ])->assertOk()->assertJsonPath('data.description', 'تفاصيل جديدة');
        $this->edit($order, ['vehicle' => [...$vehicle, 'car_name_id' => 999999]])->assertUnprocessable();
        $this->assertSame($snapshot, $order->fresh()->vehicleDetails->getAttributes());
    }

    public function test_accepted_offer_remains_protected_even_after_cancellation(): void
    {
        $order = $this->order(['status' => OrderStatus::Cancelled]);
        $offer = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => Store::factory()->create()->id]);
        $order->update(['accepted_offer_id' => $offer->id]);
        $this->remove($order)->assertConflict();
        $this->assertNotSoftDeleted($order);
    }

    public function test_every_order_status_controls_edit_delete_and_response_flags(): void
    {
        foreach (OrderType::cases() as $type) {
            foreach (OrderStatus::cases() as $status) {
                $order = $this->order(['order_type' => $type, 'status' => $status, 'notes' => 'Original']);
                $canEdit = $status === OrderStatus::Pending;
                $canDelete = in_array($status, [OrderStatus::Pending, OrderStatus::Rejected, OrderStatus::Cancelled], true);

                $this->getJson('/api/customer/orders/'.$order->id)->assertOk()
                    ->assertJsonPath('data.can_edit', $canEdit)
                    ->assertJsonPath('data.can_delete', $canDelete);

                $response = $this->edit($order, [$type === OrderType::General ? 'description' : 'notes' => 'Changed']);
                if ($canEdit) {
                    $response->assertOk();
                } else {
                    $response->assertConflict()->assertJsonPath('message', __('order_management.cannot_edit'));
                }
                $this->assertSame($canEdit ? 'Changed' : 'Original', $order->fresh()->notes);

                $response = $this->remove($order);
                if ($canDelete) {
                    $response->assertOk();
                    $this->assertSoftDeleted($order);
                } else {
                    $response->assertConflict()->assertJsonPath('message', __('order_management.cannot_delete'));
                    $this->assertNotSoftDeleted($order);
                }
            }
        }
    }

    public function test_soft_deleted_payment_history_still_protects_orders(): void
    {
        foreach (PaymentStatus::cases() as $status) {
            $order = $this->order(['notes' => 'Original']);
            $payment = Payment::factory()->create(['order_id' => $order->id, 'payment_status' => $status]);
            $payment->delete();

            $this->getJson('/api/customer/orders/'.$order->id)->assertOk()
                ->assertJsonPath('data.can_edit', false)->assertJsonPath('data.can_delete', false);
            $this->getJson('/api/customer/orders')->assertOk()
                ->assertJsonPath('data.0.can_edit', false)->assertJsonPath('data.0.can_delete', false);
            $this->edit($order, ['description' => 'Changed'])->assertConflict();
            $this->remove($order)->assertConflict();
            $this->assertSame('Original', $order->fresh()->notes);
            $this->assertNotSoftDeleted($order);
        }
    }

    public function test_status_is_rechecked_when_service_receives_a_stale_order(): void
    {
        $order = $this->order(['notes' => 'Original']);
        $token = CustomerOrderManagement::token($order);
        Order::whereKey($order->id)->update(['status' => OrderStatus::Paid]);
        $management = app(CustomerOrderManagement::class);

        foreach (['update', 'delete'] as $action) {
            try {
                if ($action === 'update') {
                    $management->update($order, ['edit_token' => $token, 'description' => 'Changed']);
                } else {
                    $management->delete($order, $token);
                }
                $this->fail('A stale pending order must not bypass the current status.');
            } catch (BusinessRuleException $exception) {
                $this->assertSame(409, $exception->statusCode());
                $this->assertSame('order_management.cannot_'.($action === 'update' ? 'edit' : 'delete'), $exception->messageKey());
            }
        }

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame('Original', $order->fresh()->notes);
        $this->assertNotSoftDeleted($order);
    }
}
