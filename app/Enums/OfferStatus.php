<?php

namespace App\Enums;

/**
 * Defines the status of an offer submitted by a store on an order.
 *
 * Used in: order_offers.status column
 *
 * - Pending: The offer is awaiting the customer's decision
 * - Accepted: The customer accepted this offer (chose this store)+
 * - Rejected: The customer explicitly rejected this offer
 * - NotSelected: Another offer was accepted by the customer
 */
enum OfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case NotSelected = 'not_selected';
}
