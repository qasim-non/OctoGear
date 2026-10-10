<?php

namespace App\Http\Requests\Shared;

use App\Http\Requests\BaseRequest;
use App\Models\Conversation;
use Illuminate\Support\Facades\Gate;

class AuthorizeChatChannelRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('subscribeSession', [
            Conversation::class, $this->input('channel_name'),
        ]);
    }

    public function rules(): array
    {
        return [
            'socket_id' => ['required', 'string', 'max:100', 'regex:/\A[0-9]+\.[0-9]+\z/'],
            'channel_name' => ['required', 'string', 'max:100'],
        ];
    }
}
