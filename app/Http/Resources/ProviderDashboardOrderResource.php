<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Services\ChatEligibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderDashboardOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $locale = $request->header('Accept-Language', app()->getLocale()) === 'en' ? 'en' : 'ar';
        $store = $order->fulfillmentStore();
        $accepted = in_array($order->status, [OrderStatus::AwaitingPayment, OrderStatus::Paid, OrderStatus::Completed]);
        $quantity = $order->isSpecific() && $order->quantity > 0 ? $order->quantity : null;
        $unit = $order->status === OrderStatus::Pending ? $order->requested_unit_price : ($order->offered_price ?? $order->requested_unit_price);
        $total = $order->isGeneral() ? $order->acceptedOffer?->price : ($unit === null || $quantity === null ? null : $unit * $quantity);
        if (in_array($order->status, [OrderStatus::Paid, OrderStatus::Completed])) {
            // Historical paid orders without a successful payment record do
            // not display an estimated price as if it were a recorded payment.
            $total = $order->payment?->payment_status === PaymentStatus::Paid ? $order->payment->amount : null;
        }

        return [
            'id' => $order->id,
            'order_type' => $order->order_type->value,
            'status' => $order->status->value,
            'part_name' => $order->isGeneral() ? $order->requestedComponentName($locale) : $order->storeCarComponent?->component?->{'name_'.$locale},
            'store' => $store ? ['id' => $store->id, 'name' => $store->name] : null,
            'customer_name' => $accepted ? $order->customer?->full_name : null,
            'quantity' => $quantity,
            'total_amount' => $total,
            'currency' => 'SAR', 'price_scale' => 100,
            'accepted_offer_id' => $order->isGeneral() ? $order->accepted_offer_id : null,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'can_chat' => app(ChatEligibility::class)->canStartProviderOrder($order, $request->user()),
        ];
    }
}
