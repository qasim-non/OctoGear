<?php

namespace App\Http\Requests\Reference;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class ReferenceCatalogRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'section_id' => ['sometimes', 'integer', Rule::exists('car_sections', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->query('search'))) {
            $this->merge(['search' => trim($this->query('search'))]);
        }
    }
}
