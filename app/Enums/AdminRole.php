<?php

namespace App\Enums;

/**
 * Defines the role assigned to an admin/sub-admin.
 *
 * Used in: admin.assigned_role column
 *
 * Different roles = different permissions in the admin panel.
 */
enum AdminRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Employee = 'employee';
    case Hr = 'hr';
    case Developer = 'developer';
}
