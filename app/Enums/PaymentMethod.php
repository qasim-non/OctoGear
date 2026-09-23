<?php

namespace App\Enums;

/**
 * Defines how the customer pays for an order.
 *
 * Used in: payments.payment_method column
 *
 * - Cash: Pay on pickup/delivery
 * - CreditCard: Pay via card (online payment gateway)
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case CreditCard = 'credit_card';
}
