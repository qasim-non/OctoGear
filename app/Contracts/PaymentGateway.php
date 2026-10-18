<?php

namespace App\Contracts;

use App\Data\PaymentCharge;
use App\Data\PaymentRefund;

interface PaymentGateway
{
    /** Return only after a confirmed charge. Never log the card token. */
    public function charge(PaymentCharge $charge): void;

    /** Return only after a confirmed refund. */
    public function refund(PaymentRefund $refund): void;
}
