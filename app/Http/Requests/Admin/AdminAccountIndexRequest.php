<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class AdminAccountIndexRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', Rule::enum(AdminRole::class)],
            'status' => ['nullable', Rule::enum(AdminStatus::class)], 'page' => ['sometimes', 'integer', 'min:1']];
    }
}
