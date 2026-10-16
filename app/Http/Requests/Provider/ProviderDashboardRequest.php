<?php

namespace App\Http\Requests\Provider;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class ProviderDashboardRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'store_id' => ['sometimes', 'nullable', 'integer', 'min:1', Rule::exists('stores', 'id')
                ->where('user_id', $this->user()->id)->whereNull('deleted_at')],
            'period' => ['sometimes', Rule::in(['today', 'week', 'month'])],
            'section' => [$this->is('api/provider/dashboard/orders') ? 'required' : 'sometimes', Rule::in(['pending_direct', 'awaiting_payment', 'paid', 'recent'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
