<?php

namespace App\Http\Requests\Shared;

use App\Http\Requests\BaseRequest;

class MarkConversationReadRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('conversation'));
    }

    public function rules(): array
    {
        return ['through_id' => ['required', 'integer', 'min:1']];
    }
}
