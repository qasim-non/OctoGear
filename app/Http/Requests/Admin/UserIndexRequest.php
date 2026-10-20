<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['customer', 'service provider'])],
            'status' => ['nullable', Rule::in(['unblocked', 'blocked'])],
            'name' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'city_id' => ['nullable', 'integer', 'min:1', Rule::exists('cities', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => __('auth.validation.admin.users.type_invalid'),
            'status.in' => __('auth.validation.admin.users.status_invalid'),
            'name.max' => __('auth.validation.admin.name.max'),
            'mobile.max' => __('auth.validation.admin.mobile.max'),
            'city_id.integer' => __('auth.validation.city_id.integer'),
            'city_id.min' => __('auth.validation.city_id.integer'),
            'city_id.exists' => __('auth.validation.city_id.exists'),
        ];
    }
}
