<?php

namespace App\Enums;

/**
 * Defines the account status of a user.
 *
 * Used in: users.status column
 *
 * - Unblocked: Normal active user, can use the app
 * - Blocked: Admin blocked this user, cannot access the app
 */
enum UserStatus: string
{
    case Unblocked = 'unblocked';
    case Blocked = 'blocked';
}
