<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\BaseRequest;
use App\Support\ImageRules;
use Illuminate\Validation\Rule;

class UpdateProviderStoreRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage', $this->route('store'));
    }

    protected function prepareForValidation(): void
    {
        // Multipart has no empty-array representation. An explicitly empty
        // field clears the selection; an omitted field preserves it.
        foreach (['company_ids', 'retained_picture_ids'] as $field) {
            if ($this->exists($field) && ($this->input($field) === null || $this->input($field) === '')) {
                $this->merge([$field => []]);
            }
        }

    }

    public function rules(): array
    {
        $storeId = $this->route('store')->getKey();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'mobile' => ['prohibited'],
            'change_reason' => ['required', 'string', 'min:10', 'max:1000'],
            'base_version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'idempotency_key' => ['required', 'uuid'],
            'nick_name' => ['sometimes', 'required', 'string', 'max:100'],
            'employee_name' => ['sometimes', 'required', 'string', 'max:100'],
            'url_location' => ['sometimes', 'required', 'url:https', 'max:255'],
            'commercial_registration_number' => ['sometimes', 'required', 'string', 'max:50'],
            'commercial_registration_picture' => ['sometimes', ...ImageRules::file()],
            'pictures' => ['sometimes', 'array', 'max:'.config('images.max_files')],
            'company_ids' => ['sometimes', 'array', 'max:100'],
            'company_ids.*' => ['required', 'integer', 'distinct', 'exists:cars_companies,id'],
            'retained_picture_ids' => ['sometimes', 'array', 'max:'.config('images.max_files')],
            'retained_picture_ids.*' => ['required', 'integer', 'distinct', Rule::exists('store_pictures', 'id')->where('store_id', $storeId)],
            'pictures.*' => ['required', ...ImageRules::file()],
            'city_id' => ['sometimes', 'required', 'integer', 'exists:cities,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.prohibited' => __('store_changes.mobile_locked'),
            'change_reason.required' => __('store_changes.reason_required'),
            'change_reason.min' => __('store_changes.reason_min'),
            'name.required' => __('auth.store.validation.name.required'),
            'name.max' => __('auth.store.validation.name.max'),
            'nick_name.required' => __('auth.store.validation.nick_name.required'),
            'nick_name.max' => __('auth.store.validation.nick_name.max'),
            'employee_name.required' => __('auth.store.validation.employee_name.required'),
            'employee_name.max' => __('auth.store.validation.employee_name.max'),
            'url_location.required' => __('auth.store.validation.url_location.required'),
            'url_location.max' => __('auth.store.validation.url_location.max'),
            'commercial_registration_number.required' => __('auth.store.validation.commercial_registration_number.required'),
            'commercial_registration_number.max' => __('auth.store.validation.commercial_registration_number.max'),
            'commercial_registration_picture.required' => __('auth.store.validation.commercial_registration_picture.required'),
            'commercial_registration_picture.file' => __('auth.validation.pictures.file'),
            'commercial_registration_picture.image' => __('auth.validation.pictures.image'),
            'commercial_registration_picture.mimes' => __('auth.validation.pictures.mimes'),
            'commercial_registration_picture.dimensions' => __('auth.validation.pictures.dimensions'),
            'city_id.required' => __('auth.validation.city_id.required'),
            'city_id.integer' => __('auth.validation.city_id.integer'),
            'city_id.exists' => __('auth.validation.city_id.exists'),
        ];
    }
}
