<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class StoreRequestIndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['pending', 'accepted', 'rejected'])],
            'search' => ['nullable', 'string', 'max:100'],
            'request_type' => ['nullable', Rule::in(['new_store', 'store_change'])],
            'city_id' => ['nullable', 'integer', 'min:1', Rule::exists('cities', 'id')->whereNull('deleted_at')],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => __('auth.validation.store_requests.status_invalid'),
            'search.max' => __('auth.validation.store_requests.search_max'),
            'request_type.in' => __('auth.validation.store_requests.type_invalid'),
            'city_id.integer' => __('auth.validation.city_id.integer'),
            'city_id.min' => __('auth.validation.city_id.integer'),
            'city_id.exists' => __('auth.validation.city_id.exists'),
            'page.integer' => __('auth.validation.store_requests.page_invalid'),
            'page.min' => __('auth.validation.store_requests.page_invalid'),
        ];
    }
}
