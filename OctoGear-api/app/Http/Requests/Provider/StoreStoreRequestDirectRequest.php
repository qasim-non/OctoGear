<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\BaseRequest;
use App\Support\MobileNumber;

class StoreStoreRequestDirectRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile' => MobileNumber::normalize($this->input('mobile')) ?? $this->input('mobile'),
        ]);
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'regex:/^\+9665\d{8}$/', 'max:15'],
            'name' => ['required', 'string', 'max:100'],
            'nick_name' => ['required', 'string', 'max:100'],
            'employee_name' => ['required', 'string', 'max:100'],
            'url_location' => ['required', 'string', 'max:255'],
            'commercial_registration_number' => ['required', 'string', 'max:50'],
            'commercial_registration_picture' => ['required', 'string', 'max:255'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
        ];
    }
}
