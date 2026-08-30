<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'order_id'       => $this->order_id,
            'amount'         => $this->amount,
            'payment_method' => $this->payment_method->value,
            'payment_status' => $this->payment_status->value,
            'created_at'     => $this->created_at,
        ];
    }
}
