<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;

class StoreCustomerCarRequest extends BaseRequest
{
    use CustomerCarPictureRules;

    protected function prepareForValidation(): void
    {
        // The key belongs in the HTTP header. Merging it here lets the normal
        // FormRequest failure envelope report a safe 422 error when it is
        // missing or malformed without accepting a body-provided substitute.
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    public function rules(): array
    {
        $year = date('Y');

        return [
            'car_name_id' => ['required', 'integer', 'exists:cars_names,id'],
            'manufacturing_year' => ['required', 'integer', 'min:1970', "max:$year"],
            'vehicle_plat_number' => ['required', 'string', 'max:50'],
            'color_id' => ['required', 'integer', 'exists:colors,id'],
            'fuel_type' => ['required', 'integer', 'exists:fuel_types,id'],
            'idempotency_key' => ['required', 'uuid'],
            'pictures' => ['nullable', 'array', 'max:'.config('customer_car_media.max_files')],
            'pictures.*' => self::pictureFileRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'car_name_id.required' => __('auth.validation.car_name_id.required'),
            'car_name_id.integer' => __('auth.validation.car_name_id.integer'),
            'car_name_id.exists' => __('auth.validation.car_name_id.exists'),
            'manufacturing_year.required' => __('auth.validation.manufacturing_year.required'),
            'manufacturing_year.integer' => __('auth.validation.manufacturing_year.integer'),
            'manufacturing_year.min' => __('auth.validation.manufacturing_year.min'),
            'manufacturing_year.max' => __('auth.validation.manufacturing_year.max'),
            'vehicle_plat_number.required' => __('auth.validation.vehicle_plat_number.required'),
            'vehicle_plat_number.max' => __('auth.validation.vehicle_plat_number.max'),
            'color_id.required' => __('auth.validation.color_id.required'),
            'color_id.integer' => __('auth.validation.color_id.integer'),
            'color_id.exists' => __('auth.validation.color_id.exists'),
            'fuel_type.required' => __('auth.validation.fuel_type.required'),
            'fuel_type.integer' => __('auth.validation.fuel_type.integer'),
            'fuel_type.exists' => __('auth.validation.fuel_type.exists'),
            'idempotency_key.required' => __('auth.validation.idempotency_key.required'),
            'idempotency_key.uuid' => __('auth.validation.idempotency_key.uuid'),
            ...self::pictureMessages(),
        ];
    }
}
