<?php

namespace App\Http\Requests\Shared;

use App\Http\Requests\BaseRequest;

class PaginationRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1']];
    }
}
