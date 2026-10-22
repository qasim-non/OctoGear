<?php

namespace App\Support;

use App\Enums\AdminRole;

final class AdminPermissions
{
    public static function forRole(AdminRole $role): array
    {
        return match ($role) {
            AdminRole::Admin => ['dashboard.view', 'users.view', 'users.manage', 'stores.view', 'stores.manage', 'requests.view', 'requests.manage', 'orders.view', 'orders.manage', 'payments.view', 'payments.manage', 'support.access', 'catalog.view', 'catalog.manage', 'admins.view', 'admins.manage'],
            AdminRole::Manager => ['dashboard.view', 'users.view', 'users.manage', 'stores.view', 'stores.manage', 'requests.view', 'requests.manage', 'orders.view', 'payments.view', 'support.access', 'catalog.view', 'catalog.manage'],
            AdminRole::Employee => ['users.view', 'stores.view', 'orders.view', 'support.access'],
            AdminRole::Hr => ['admins.view'],
            AdminRole::Developer => ['catalog.view', 'catalog.manage'],
        };
    }
}
