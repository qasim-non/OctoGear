<?php

namespace App\Http\Requests\Customer;

use App\Enums\OrderStatus;
use App\Http\Requests\BaseRequest;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class StoreRatingRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'rating'   => ['required', 'integer', 'between:1,5'],
            'comment'  => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $order = Order::where('id', $this->order_id)
                ->where('customer_id', Auth::id())
                ->first();

            if (!$order) {
                $validator->errors()->add('order_id', __('auth.validation.rating.order_not_found'));
                return;
            }

            if ($order->status !== OrderStatus::Completed) {
                $validator->errors()->add('order_id', __('auth.validation.rating.order_not_completed'));
                return;
            }

            if ((int) $this->store_id !== $order->accepted_store_id) {
                $validator->errors()->add('store_id', __('auth.validation.rating.store_mismatch'));
                return;
            }

            if ($order->rating()->withTrashed()->exists()) {
                $validator->errors()->add('order_id', __('auth.validation.rating.already_rated'));
            }
        });
    }
}
