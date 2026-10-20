<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreIndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'name' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'owner_id' => ['nullable', 'integer', 'min:1', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => __('auth.validation.admin.stores.status_invalid'),
            'name.max' => __('auth.validation.admin.name.max'),
            'mobile.max' => __('auth.validation.admin.mobile.max'),
            'owner_id.integer' => __('auth.validation.admin.stores.owner_invalid'),
            'owner_id.min' => __('auth.validation.admin.stores.owner_invalid'),
            'owner_id.exists' => __('auth.validation.admin.stores.owner_missing'),
        ];
    }
}
