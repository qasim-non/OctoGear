<?php

namespace App\Http\Requests\Support;

use App\Models\SupportConversation;

class AuthorizeSupportChannelRequest extends SupportRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('subscribeSession', [SupportConversation::class, $this->input('channel_name')]);
    }

    public function rules(): array
    {
        return ['socket_id' => ['required', 'string', 'max:100', 'regex:/\A[0-9]+\.[0-9]+\z/'], 'channel_name' => ['required', 'string', 'max:100']];
    }
}
