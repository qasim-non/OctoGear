<?php

namespace App\Http\Requests\Shared;

use App\Enums\PreferredLanguage;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateLanguageRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['preferred_language' => ['required', Rule::enum(PreferredLanguage::class)]];
    }

    public function messages(): array
    {
        return [
            'preferred_language.required' => __('admin_web.language_invalid'),
            'preferred_language.Illuminate\\Validation\\Rules\\Enum' => __('admin_web.language_invalid'),
        ];
    }
}
