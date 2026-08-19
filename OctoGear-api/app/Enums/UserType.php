<?php

namespace App\Enums;

/**
 * Defines the type of user in the system.
 *
 * Used in: users.type column
 *
 * - Customer: Buys car parts, places orders, rates stores
 * - Service Provider: Owns a store, sells car parts, responds to orders
 */
enum UserType: string
{
    case Customer = 'customer';
    case ServiceProvider = 'service provider';
}
