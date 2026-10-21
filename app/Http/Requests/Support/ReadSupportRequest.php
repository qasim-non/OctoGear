<?php

namespace App\Http\Requests\Support;

class ReadSupportRequest extends SupportRequest
{
    public function rules(): array
    {
        return ['through_id' => ['required', 'integer', 'min:1']];
    }
}
