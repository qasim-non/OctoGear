<?php

namespace App\Enums;

/**
 * Defines the status of a payment.
 *
 * Used in: payments.payment_status column
 *
 * - Pending: Payment initiated, waiting for confirmation
 * - Paid: Payment confirmed successfully
 * - Failed: Payment failed (card declined, etc.)
 * - Refunded: Payment was refunded to customer
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
