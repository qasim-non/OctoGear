<?php

namespace App\Http\Requests\Support;

class SupportTimelineRequest extends SupportRequest
{
    public function rules(): array
    {
        return ['before_id' => ['sometimes', 'integer', 'min:1', 'prohibits:after_id'], 'after_id' => ['sometimes', 'integer', 'min:0']];
    }
}
