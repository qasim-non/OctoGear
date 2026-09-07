<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StoreStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Rating;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;

/**
 * Reads the aggregate numbers shown on the admin dashboard home screen.
 */
class AdminDashboardService
{
    public function overview(): array
    {
        return [
            'users' => [
                'total' => User::count(),
                'customers' => User::where('type', UserType::Customer)->count(),
                'providers' => User::where('type', UserType::ServiceProvider)->count(),
                'blocked' => User::where('status', UserStatus::Blocked)->count(),
            ],
            'stores' => [
                'total' => Store::withTrashed()->count(),
                'active' => Store::where('status', StoreStatus::Active)->count(),
                'inactive' => Store::where('status', StoreStatus::Inactive)->count(),
                'pending_requests' => StoreRequest::where('request_status', 'pending')->count(),
            ],
            'orders' => [
                'total' => Order::count(),
                'pending' => Order::where('status', OrderStatus::Pending)->count(),
                'negotiating' => Order::where('status', OrderStatus::Negotiating)->count(),
                'paid' => Order::where('status', OrderStatus::Paid)->count(),
                'completed' => Order::where('status', OrderStatus::Completed)->count(),
                'cancelled' => Order::where('status', OrderStatus::Cancelled)->count(),
            ],
            'revenue' => (int) Payment::where('payment_status', 'paid')->sum('amount'),
            'ratings' => [
                'total' => Rating::count(),
                'average' => round((float) Rating::avg('rating'), 2),
            ],
        ];
    }
}
