<?php

namespace App\Http\Requests\Support;

class SendSupportMessageRequest extends SupportRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('content'))) {
            $this->merge(['content' => trim($this->input('content'))]);
        }
    }

    public function rules(): array
    {
        return ['content' => ['required', 'string', 'max:2000'], 'client_message_id' => ['required', 'uuid']];
    }
}
