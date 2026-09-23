<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\BaseRequest;
use App\Support\MobileNumber;
use Illuminate\Validation\Rule;

class UpdateProviderStoreRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $mobile = $this->input('mobile');

        if ($mobile !== null) {
            $this->merge([
                'mobile' => MobileNumber::normalize($mobile) ?? $mobile,
            ]);
        }
    }

    public function rules(): array
    {
        $storeId = auth()->user()?->stores()->value('id');

        return [
            'name'                             => ['sometimes', 'string', 'max:100'],
            'mobile'                           => ['sometimes', 'string', 'regex:/^\+9665\d{8}$/', 'max:15', Rule::unique('stores', 'mobile')->ignore($storeId)],
            'nick_name'                        => ['sometimes', 'string', 'max:100'],
            'employee_name'                    => ['sometimes', 'string', 'max:100'],
            'url_location'                     => ['sometimes', 'string', 'max:255'],
            'commercial_registration_number'   => ['sometimes', 'string', 'max:50'],
            'commercial_registration_picture'  => ['sometimes', 'string', 'max:255'],
            'city_id'                          => ['sometimes', 'integer', 'exists:cities,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                            => __('auth.store.validation.name.required'),
            'name.max'                                 => __('auth.store.validation.name.max'),
            'mobile.required'                          => __('auth.store.validation.mobile.required'),
            'mobile.unique'                            => __('auth.store.validation.mobile.unique'),
            'mobile.regex'                             => __('auth.store.validation.mobile.regex'),
            'mobile.max'                               => __('auth.store.validation.mobile.max'),
            'nick_name.required'                       => __('auth.store.validation.nick_name.required'),
            'nick_name.max'                            => __('auth.store.validation.nick_name.max'),
            'employee_name.required'                   => __('auth.store.validation.employee_name.required'),
            'employee_name.max'                        => __('auth.store.validation.employee_name.max'),
            'url_location.required'                    => __('auth.store.validation.url_location.required'),
            'url_location.max'                         => __('auth.store.validation.url_location.max'),
            'commercial_registration_number.required'  => __('auth.store.validation.commercial_registration_number.required'),
            'commercial_registration_number.max'       => __('auth.store.validation.commercial_registration_number.max'),
            'commercial_registration_picture.required' => __('auth.store.validation.commercial_registration_picture.required'),
            'commercial_registration_picture.max'      => __('auth.store.validation.commercial_registration_picture.max'),
            'city_id.required'                         => __('auth.validation.city_id.required'),
            'city_id.integer'                          => __('auth.validation.city_id.integer'),
            'city_id.exists'                           => __('auth.validation.city_id.exists'),
        ];
    }
}
