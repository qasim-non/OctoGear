<?php

namespace App\Integrations\Payments;

use App\Contracts\PaymentGateway;
use App\Data\PaymentCharge;
use App\Data\PaymentRefund;
use Illuminate\Support\Manager;
use RuntimeException;

final class PaymentGatewayManager extends Manager implements PaymentGateway
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('payments.driver', 'stub');
    }

    protected function createDriver($driver): PaymentGateway
    {
        $class = $this->config->get("payments.drivers.{$driver}");

        if (! is_string($class) || ! is_a($class, PaymentGateway::class, true)) {
            throw new RuntimeException("Payment driver [{$driver}] is not implemented.");
        }

        return $this->container->make($class);
    }

    public function charge(PaymentCharge $charge): void
    {
        $this->driver()->charge($charge);
    }

    public function refund(PaymentRefund $refund): void
    {
        $this->driver()->refund($refund);
    }
}
