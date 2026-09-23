<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseRequest;

class AcceptOfferRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'offer_id' => ['required', 'integer', 'exists:order_offers,id'],
        ];
    }
}
