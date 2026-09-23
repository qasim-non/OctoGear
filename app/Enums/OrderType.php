<?php

namespace App\Enums;

/**
 * Defines the type of order.
 *
 * Used in: orders.order_type column
 *
 * - General: Customer needs a part, broadcast to all stores in their city.
 *            Multiple stores can submit competing offers.
 * - Specific: Customer targets a specific part from a specific store.
 *             Only that one store receives the order.
 */
enum OrderType: string
{
    case General = 'general';
    case Specific = 'specific';
}
