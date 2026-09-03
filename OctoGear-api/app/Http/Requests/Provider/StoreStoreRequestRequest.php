<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\BaseRequest;

class StoreStoreRequestRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'temp_token'                       => ['required', 'string'],
            'name'                             => ['required', 'string', 'max:100'],
            'nick_name'                        => ['required', 'string', 'max:100'],
            'employee_name'                    => ['required', 'string', 'max:100'],
            'url_location'                     => ['required', 'string', 'max:255'],
            'commercial_registration_number'   => ['required', 'string', 'max:50'],
            'commercial_registration_picture'  => ['required', 'string', 'max:255'],
            'city_id'                          => ['required', 'integer', 'exists:cities,id'],
        ];
    }
}
