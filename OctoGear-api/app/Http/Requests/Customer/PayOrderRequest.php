<?php

namespace App\Http\Requests\Customer;

use App\Enums\PaymentMethod;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class PayOrderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'card_token'     => ['required_if:payment_method,credit_card', 'string', 'max:255'],
        ];
    }
}
