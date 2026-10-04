<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\CarName;
use App\Models\Color;
use App\Models\Component;
use App\Models\FuelType;
use App\Models\Order;
use App\Models\User;

class GeneralOrderDetailsService
{
    public function __construct(private CustomerCarService $cars) {}

    /** Called inside the order transaction, after checking for a submission replay. */
    public function attach(Order $order, User $customer, array $data): void
    {
        $this->attachVehicle($order, $customer, $data);
        $this->attachComponent($order, $data);
    }

    /** Called inside the locked order-update transaction; never modifies the garage. */
    public function replaceVehicle(Order $order, array $vehicle): void
    {
        $order->vehicleDetails()->delete();
        $this->attachVehicle($order, $order->customer, ['vehicle' => $vehicle]);
    }

    /** Creation may select/save a garage car; updates only pass inline vehicle details. */
    private function attachVehicle(Order $order, User $customer, array $data): void
    {
        if (isset($data['customer_car_id'])) {
            $car = $customer->customerCars()->lockForUpdate()->find($data['customer_car_id']);
            if (! $car) {
                $this->invalid('customer_car_id');
            }
            $name = CarName::withTrashed()->with(['carCompany' => fn ($query) => $query->withTrashed()])->find($car->car_name_id);
            $color = Color::find($car->color_id);
            $fuel = FuelType::find($car->fuel_type);
            if (! $name || ! $name->carCompany || ! $color || ! $fuel) {
                throw new BusinessRuleException(
                    messageKey: 'auth.general.validation_failed', statusCode: 422,
                    errors: ['customer_car_id' => [__('auth.validation.general_order.complete_car')]],
                );
            }
            $vehicle = [
                'customer_car_id' => $car->id,
                'manufacturing_year' => $car->manufacturing_year,
                'transmission_type' => $car->transmission_type,
            ];
        } else {
            $name = CarName::with('carCompany')->find($data['vehicle']['car_name_id']);
            if (! $name || ! $name->carCompany) {
                $this->invalid('vehicle.car_name_id');
            }
            $color = Color::find($data['vehicle']['color_id']);
            $fuel = FuelType::find($data['vehicle']['fuel_type']);
            if (! $color) {
                $this->invalid('vehicle.color_id');
            }
            if (! $fuel) {
                $this->invalid('vehicle.fuel_type');
            }
            $vehicle = [
                'manufacturing_year' => $data['vehicle']['manufacturing_year'],
                'transmission_type' => $data['vehicle']['transmission_type'],
                'color_id' => $color->id,
                'fuel_type' => $fuel->id,
            ];
            if ($data['save_to_my_cars'] ?? false) {
                $car = $this->cars->saveRequestVehicle($customer, [...$vehicle, 'car_name_id' => $name->id]);
                $vehicle['customer_car_id'] = $car->id;
            }
        }

        $order->vehicleDetails()->create([
            ...$vehicle,
            'color_id' => $color->id,
            'fuel_type' => $fuel->id,
            'color_name_en' => $color->name_en,
            'color_name_ar' => $color->name_ar,
            'fuel_type_en' => $fuel->type_en,
            'fuel_type_ar' => $fuel->type_ar,
            'car_name_id' => $name?->id,
            'car_company_id' => $name?->carCompany?->id,
            'car_name_en' => $name?->name_en,
            'car_name_ar' => $name?->name_ar,
            'company_name_en' => $name?->carCompany?->name_en,
            'company_name_ar' => $name?->carCompany?->name_ar,
        ]);

    }

    public function attachComponent(Order $order, array $data): void
    {
        if (isset($data['component_id'])) {
            $component = Component::with('section')->find($data['component_id']);
            if (! $component || ! $component->section) {
                $this->invalid('component_id');
            }
            $order->update([
                'component_id' => $component->id,
                'component_name' => null,
            ]);
        } else {
            $order->update([
                'component_name' => $data['component_name'],
                'component_id' => null,
            ]);
        }
    }

    private function invalid(string $field): never
    {
        throw new BusinessRuleException(
            messageKey: 'auth.general.validation_failed', statusCode: 422,
            errors: [$field => [__('auth.validation.general_order.invalid_reference')]],
        );
    }
}
