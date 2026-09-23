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
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => __('auth.validation.store_requests.status_invalid'),
        ];
    }
}
