<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class VehicleCatalogDeleteRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['version' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']];
    }

    public function messages(): array
    {
        return ['version.*' => __('admin_catalog.stale')];
    }
}
