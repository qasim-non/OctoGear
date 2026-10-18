<?php

namespace App\Services;

use App\Models\Order;

final class OrderPricingService
{
    public function amountFor(Order $order): int
    {
        if ($order->isGeneral()) {
            return (int) ($order->acceptedOffer?->price ?? 0);
        }

        return ($order->offered_price ?? 0) * max(1, (int) $order->quantity);
    }

    public function commissionForAmount(int $amount): int
    {
        return (int) round($amount * config('payments.commission_rate') / 100);
    }
}
