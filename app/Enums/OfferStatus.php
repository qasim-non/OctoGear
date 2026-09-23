<?php

namespace App\Enums;

/**
 * Defines the status of an offer submitted by a store on an order.
 *
 * Used in: order_offers.status column
 *
 * - Pending: The offer is awaiting the customer's decision
 * - Accepted: The customer accepted this offer (chose this store)
 * - Rejected: The customer rejected this offer
 */
enum OfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
