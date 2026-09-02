<?php

namespace App\Http\Requests\Api\OrderOffer;

use App\Http\Requests\BaseRequest;

class RejectOfferRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'rejection_reason' => ['sometimes', 'string', 'max:1000'],
        ];
    }
}