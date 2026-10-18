<?php

namespace App\Integrations\Payments;

use App\Contracts\PaymentGateway;
use App\Data\PaymentCharge;
use App\Data\PaymentRefund;

/** Simulation only: no funds are transferred. */
final class StubPaymentGateway implements PaymentGateway
{
    public function charge(PaymentCharge $charge): void {}

    public function refund(PaymentRefund $refund): void {}
}
