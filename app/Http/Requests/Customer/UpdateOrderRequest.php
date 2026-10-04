<?php

namespace App\Http\Requests\Customer;

use App\Enums\TransmissionType;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends BaseRequest
{
    public function rules(): array
    {
        $general = $this->route('order')->isGeneral();

        return [
            'edit_token' => ['required', 'string', 'size:64'],
            'description' => $general ? ['sometimes', 'nullable', 'string', 'max:1000'] : ['prohibited'],
            'notes' => ! $general ? ['sometimes', 'nullable', 'string', 'max:1000'] : ['prohibited'],
            'quantity' => ! $general ? ['sometimes', 'required', 'integer', 'min:1', 'max:2147483647'] : ['prohibited'],
            'component_id' => $general ? ['sometimes', 'required', 'integer', 'min:1', 'prohibits:component_name'] : ['prohibited'],
            'component_name' => $general ? ['sometimes', 'required', 'string', 'max:255', 'prohibits:component_id'] : ['prohibited'],
            'customer_car_id' => ['prohibited'],
            'vehicle' => $general ? ['sometimes', 'required', 'array:car_name_id,manufacturing_year,transmission_type,color_id,fuel_type'] : ['prohibited'],
            'vehicle.car_name_id' => ['required_with:vehicle', 'integer', 'min:1'],
            'vehicle.color_id' => ['required_with:vehicle', 'integer', 'min:1'],
            'vehicle.fuel_type' => ['required_with:vehicle', 'integer', 'min:1'],
            'vehicle.manufacturing_year' => ['required_with:vehicle', 'integer', 'min:1970', 'max:'.date('Y')],
            'vehicle.transmission_type' => ['required_with:vehicle', Rule::enum(TransmissionType::class)],
            'order_type' => ['prohibited'],
            'store_car_component_id' => ['prohibited'],
            'status' => ['prohibited'],
            'offered_price' => ['prohibited'],
            'customer_id' => ['prohibited'],
            'images' => ['prohibited'],
            'save_to_my_cars' => ['prohibited'],
        ];
    }
}
