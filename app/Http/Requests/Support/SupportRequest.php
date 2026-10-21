<?php

namespace App\Http\Requests\Support;

use App\Http\Requests\BaseRequest;
use App\Models\SupportConversation;

class SupportRequest extends BaseRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('supportConversation');

        return $conversation instanceof SupportConversation
            ? $this->user()->can('view', $conversation)
            : $this->user()->can('access', SupportConversation::class);
    }
}
