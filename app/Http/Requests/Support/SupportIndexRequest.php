<?php

namespace App\Http\Requests\Support;

use App\Models\SupportConversation;

class SupportIndexRequest extends SupportRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', SupportConversation::class);
    }

    protected function prepareForValidation(): void
    {
        if (in_array($this->input('unread'), ['true', 'false'], true)) {
            $this->merge(['unread' => $this->input('unread') === 'true']);
        }
    }

    public function rules(): array
    {
        return ['status' => ['sometimes', 'in:open,closed,all'], 'type' => ['sometimes', 'in:customer,service provider'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'], 'unread' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,50']];
    }
}
