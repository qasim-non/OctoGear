<?php

namespace App\Http\Requests\Customer;

use App\Enums\TransmissionType;
use App\Http\Requests\BaseRequest;
use App\Support\ImageRules;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        // Only the header supplies this server-controlled persistence field.
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        $orderType = $this->input('order_type');

        $rules = [
            'order_type' => ['required', Rule::in(['general', 'specific'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'idempotency_key' => ['nullable', 'uuid'],
            'customer_image' => ['prohibited'],
            'images' => ['sometimes', 'array', 'list', 'max:'.config('images.max_files')],
            'images.*' => ImageRules::file(),
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($orderType === 'specific') {
            $rules['store_car_component_id'] = [
                'required',
                'integer',
                'min:1',
                // Availability is checked after an idempotency replay lookup.
            ];
        } elseif ($orderType === 'general') {
            $rules['idempotency_key'] = ['required', 'uuid'];
            $rules['customer_car_id'] = ['required_without:vehicle', 'prohibits:vehicle', 'integer', 'min:1'];
            $rules['vehicle'] = ['required_without:customer_car_id', 'prohibits:customer_car_id', 'array:car_name_id,manufacturing_year,transmission_type,color_id,fuel_type'];
            $rules['vehicle.car_name_id'] = ['required_with:vehicle', 'integer', 'min:1'];
            $rules['vehicle.color_id'] = ['required_with:vehicle', 'integer', 'min:1'];
            $rules['vehicle.fuel_type'] = ['required_with:vehicle', 'integer', 'min:1'];
            $rules['vehicle.manufacturing_year'] = ['required_with:vehicle', 'integer', 'min:1970', 'max:'.date('Y')];
            $rules['vehicle.transmission_type'] = ['required_with:vehicle', 'string', Rule::enum(TransmissionType::class)];
            $rules['save_to_my_cars'] = ['sometimes', 'boolean', Rule::prohibitedIf($this->filled('customer_car_id'))];
            $rules['component_id'] = ['required_without:component_name', 'prohibits:component_name', 'integer', 'min:1'];
            $rules['component_name'] = ['required_without:component_id', 'prohibits:component_id', 'string', 'max:255'];
            $rules['description'] = ['nullable', 'string', 'max:1000'];
            foreach (['quantity', 'model_id', 'notes', 'store_car_component_id'] as $field) {
                $rules[$field] = ['prohibited'];
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'customer_image.prohibited' => __('auth.validation.order_images.use_images'),
            'images.array' => __('auth.validation.order_images.array'),
            'images.list' => __('auth.validation.order_images.array'),
            'images.max' => __('auth.validation.order_images.max', ['max' => config('images.max_files')]),
            'images.*.file' => __('auth.validation.pictures.file'),
            'images.*.image' => __('auth.validation.pictures.image'),
            'images.*.mimes' => __('auth.validation.pictures.mimes'),
            'images.*.max' => __('auth.validation.order_images.file_max', ['max' => config('images.max_file_size_kb')]),
            'images.*.dimensions' => __('auth.validation.pictures.dimensions'),
            'idempotency_key.required' => __('auth.validation.idempotency_key.required'),
            'idempotency_key.uuid' => __('auth.validation.idempotency_key.uuid'),
            'vehicle.transmission_type.string' => __('auth.validation.transmission_type.invalid'),
            'vehicle.transmission_type.enum' => __('auth.validation.transmission_type.invalid'),
            'vehicle.manufacturing_year.min' => __('auth.validation.manufacturing_year.min'),
            'vehicle.manufacturing_year.max' => __('auth.validation.manufacturing_year.max'),
            'vehicle.array' => __('auth.validation.general_order.vehicle_fields'),
            'save_to_my_cars.boolean' => __('auth.validation.general_order.save_boolean'),
            'component_name.string' => __('auth.validation.general_order.part_name'),
            'component_name.max' => __('auth.validation.general_order.part_name'),
            'description.string' => __('auth.validation.general_order.description'),
            'description.max' => __('auth.validation.general_order.description'),
        ];
        foreach (['customer_car_id', 'vehicle'] as $field) {
            $messages[$field.'.required_without'] = __('auth.validation.general_order.vehicle_choice');
            $messages[$field.'.prohibits'] = __('auth.validation.general_order.vehicle_choice');
        }
        foreach (['component_id', 'component_name'] as $field) {
            $messages[$field.'.required_without'] = __('auth.validation.general_order.part_choice');
            $messages[$field.'.prohibits'] = __('auth.validation.general_order.part_choice');
        }
        foreach (['vehicle.car_name_id', 'vehicle.manufacturing_year', 'vehicle.transmission_type', 'vehicle.color_id', 'vehicle.fuel_type'] as $field) {
            $messages[$field.'.required_with'] = __('auth.validation.general_order.vehicle_fields');
        }
        foreach (['customer_car_id', 'vehicle.car_name_id', 'vehicle.color_id', 'vehicle.fuel_type', 'component_id'] as $field) {
            $messages[$field.'.integer'] = __('auth.validation.general_order.invalid_reference');
            $messages[$field.'.min'] = __('auth.validation.general_order.invalid_reference');
        }
        $messages['vehicle.manufacturing_year.integer'] = __('auth.validation.manufacturing_year.integer');
        foreach (['quantity', 'model_id', 'notes', 'store_car_component_id', 'save_to_my_cars'] as $field) {
            $messages[$field.'.prohibited'] = __('auth.validation.general_order.prohibited');
        }

        return $messages;
    }
}
