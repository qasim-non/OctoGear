<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class ProviderManagedOrdersRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in($this->is('api/provider/orders/paid')
                ? ['paid', 'completed']
                : ['pending', 'awaiting_payment', 'paid', 'completed', 'rejected', 'cancelled'])],
        ];
    }
}
