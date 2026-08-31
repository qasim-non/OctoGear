<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class PayOrderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::in(['credit_card'])],
            'card_token'     => ['required', 'string', 'max:255'],
        ];
    }
}
