<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

class RevokeRefreshTokenRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['refresh_token' => [$this->is('api/auth/admin/*') ? 'prohibited' : 'required', 'string', 'max:128']];
    }
}
