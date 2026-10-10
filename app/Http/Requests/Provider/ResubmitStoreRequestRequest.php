<?php

namespace App\Http\Requests\Provider;

use App\Support\ImageRules;

class ResubmitStoreRequestRequest extends StoreStoreRequestRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['temp_token'] = ['prohibited'];
        $rules['mobile'] = ['prohibited'];
        $rules['commercial_registration_picture'] = ['sometimes', ...ImageRules::file()];

        return $rules;
    }
}
