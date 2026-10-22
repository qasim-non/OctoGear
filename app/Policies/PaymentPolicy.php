<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAdminDirectory(User|Admin $actor): bool
    {
        return $actor instanceof Admin && $actor->hasPermission('payments.view');
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->id === $payment->order?->customer_id;
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function restore(User $user, Payment $payment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }
}
