<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class CarNameIndexRequest extends CarCompanyIndexRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'company_id' => ['nullable', 'integer', 'min:1', Rule::exists('cars_companies', 'id')->whereNull('deleted_at')],
        ];
    }
}
