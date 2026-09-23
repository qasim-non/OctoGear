<?php

namespace App\Enums;

/**
 * Defines the account status of an admin.
 *
 * Used in: admin.status column
 *
 * - Active: Normal working admin
 * - Inactive: Temporarily disabled
 * - Blocked: Permanently blocked (cannot login)
 */
enum AdminStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';
}
