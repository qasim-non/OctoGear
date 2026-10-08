<?php

namespace App\Http\Requests\Shared;

use App\Http\Requests\BaseRequest;

class StoreMessageRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('conversation'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('content'))) {
            $this->merge(['content' => trim($this->input('content'))]);
        }
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:2000'],
            'client_message_id' => ['sometimes', 'required', 'uuid'],
        ];
    }
}
