<?php

namespace App\Http\Requests\Provider;

use App\Enums\SectionCondition;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class CreateProviderStoreCarRequest extends BaseRequest
{
    public function rules(): array
    {
        $year = date('Y');

        return [
            'car_name_id'         => ['required', 'integer', 'exists:cars_names,id'],
            'manufacturing_year'  => ['required', 'integer', 'min:1970', "max:$year"],
            'vehicle_plat_number' => ['required', 'string', 'max:50'],
            'color_id'            => ['required', 'integer', 'exists:colors,id'],
            'fuel_type'           => ['required', 'integer', 'exists:fuel_types,id'],
            'pictures'            => ['nullable', 'array'],
            'pictures.*'          => ['string', 'max:255'],

            'sections'              => ['required', 'array', 'min:1'],
            'sections.*.section_id' => ['required', 'integer', 'distinct', 'exists:car_sections,id'],
            'sections.*.condition'  => ['required', Rule::enum(SectionCondition::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'car_name_id.required'    => __('auth.validation.car_name_id.required'),
            'car_name_id.integer'     => __('auth.validation.car_name_id.integer'),
            'car_name_id.exists'      => __('auth.validation.car_name_id.exists'),
            'manufacturing_year.required' => __('auth.validation.manufacturing_year.required'),
            'manufacturing_year.integer'  => __('auth.validation.manufacturing_year.integer'),
            'manufacturing_year.min'  => __('auth.validation.manufacturing_year.min'),
            'manufacturing_year.max'  => __('auth.validation.manufacturing_year.max'),
            'vehicle_plat_number.required' => __('auth.validation.vehicle_plat_number.required'),
            'vehicle_plat_number.max' => __('auth.validation.vehicle_plat_number.max'),
            'color_id.required'       => __('auth.validation.color_id.required'),
            'color_id.integer'        => __('auth.validation.color_id.integer'),
            'color_id.exists'         => __('auth.validation.color_id.exists'),
            'fuel_type.required'      => __('auth.validation.fuel_type.required'),
            'fuel_type.integer'       => __('auth.validation.fuel_type.integer'),
            'fuel_type.exists'        => __('auth.validation.fuel_type.exists'),
            'pictures.array'          => __('auth.validation.pictures.array'),
            'pictures.*.max'          => __('auth.validation.pictures.max'),
            'sections.required'       => __('auth.validation.sections.required'),
            'sections.array'          => __('auth.validation.sections.array'),
            'sections.*.section_id.required' => __('auth.validation.sections.section_id_required'),
            'sections.*.section_id.exists'   => __('auth.validation.sections.section_id_exists'),
            'sections.*.condition.required'  => __('auth.validation.sections.condition_required'),
            'sections.*.condition.enum'      => __('auth.validation.sections.condition_enum'),
        ];
    }
}
