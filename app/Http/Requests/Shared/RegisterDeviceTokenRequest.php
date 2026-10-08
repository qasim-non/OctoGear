<?php

namespace App\Http\Requests\Shared;

use App\Http\Requests\BaseRequest;
use App\Models\DeviceToken;

class RegisterDeviceTokenRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', DeviceToken::class);
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512', 'regex:/^[A-Za-z0-9_:\-]+$/D'],
            // iOS remains deferred until its own Firebase identity is configured.
            'platform' => ['required', 'in:android'],
            'locale' => ['required', 'in:ar,en'],
        ];
    }

    public function messages(): array
    {
        return collect($this->rules())->flatMap(fn ($rules, $field) => collect($rules)
            ->mapWithKeys(fn ($rule) => [$field.'.'.explode(':', $rule)[0] => __('push.invalid')]))->all();
    }
}
