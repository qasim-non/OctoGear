<?php

namespace App\Http\Requests\Shared;

use App\Http\Requests\BaseRequest;

class ConversationTimelineRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('conversation'));
    }

    public function rules(): array
    {
        return [
            'before_id' => ['nullable', 'integer', 'min:1', 'prohibits:after_id'],
            'after_id' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
