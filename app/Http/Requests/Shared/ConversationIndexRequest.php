<?php

namespace App\Http\Requests\Shared;

class ConversationIndexRequest extends PaginationRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('with_messages')) {
            // Preserve Request::boolean() spellings used by existing clients.
            $value = filter_var($this->input('with_messages'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                $this->merge(['with_messages' => $value]);
            }
        }
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'with_messages' => ['sometimes', 'boolean'],
        ];
    }
}
