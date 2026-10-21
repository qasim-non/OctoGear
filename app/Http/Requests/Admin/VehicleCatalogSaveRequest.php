<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class VehicleCatalogSaveRequest extends BaseRequest
{
    public function rules(): array
    {
        $companies = $this->is('api/admin/car-companies*');

        return [
            'name_en' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
            ...($companies ? [
                'country_id' => ['present', 'nullable', 'integer', Rule::exists('countries', 'id')->whereNull('deleted_at')],
            ] : [
                'car_company_id' => ['required', 'integer', Rule::exists('cars_companies', 'id')->whereNull('deleted_at')],
            ]),
            'version' => [$this->isMethod('PATCH') ? 'required' : 'prohibited', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name_en.*' => __('admin_catalog.name_invalid'),
            'name_ar.*' => __('admin_catalog.name_invalid'),
            'country_id.*' => __('admin_catalog.country_invalid'),
            'car_company_id.*' => __('admin_catalog.company_invalid'),
            'version.*' => __('admin_catalog.stale'),
        ];
    }
}
