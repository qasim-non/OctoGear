<?php

namespace App\Enums;

/**
 * Defines the status of a store.
 *
 * Used in: stores.status column
 *
 * - Active: Store is live, visible to customers, can receive orders
 * - Inactive: Store is hidden from customers, cannot receive orders
 */
enum StoreStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
