<?php

namespace App\Events;

use App\Models\OrderOffer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OfferCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public OrderOffer $offer)
    {
    }
}
