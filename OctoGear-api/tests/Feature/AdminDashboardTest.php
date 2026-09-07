<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Rating;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_dashboard_overview_counts(): void
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $customer = User::factory()->customer()->create();
        Order::factory()->create(['customer_id' => $customer->id, 'status' => OrderStatus::Pending]);
        $completed = Order::factory()->create(['customer_id' => $customer->id, 'status' => OrderStatus::Completed]);
        Rating::factory()->create(['customer_id' => $customer->id, 'store_id' => $store->id, 'order_id' => $completed->id, 'rating' => 5]);
        StoreRequest::factory()->pending()->create(['user_id' => $provider->id]);
        User::factory()->blocked()->customer()->create();

        $response = $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'users' => ['total', 'customers', 'providers', 'blocked'],
                    'stores' => ['total', 'active', 'inactive', 'pending_requests'],
                    'orders' => ['total', 'pending', 'negotiating', 'paid', 'completed', 'cancelled'],
                    'revenue',
                    'ratings' => ['total', 'average'],
                ],
            ]);

        $response->assertJsonPath('data.users.total', 3)
            ->assertJsonPath('data.users.providers', 1)
            ->assertJsonPath('data.users.blocked', 1)
            ->assertJsonPath('data.stores.total', 1)
            ->assertJsonPath('data.stores.active', 1)
            ->assertJsonPath('data.stores.pending_requests', 1)
            ->assertJsonPath('data.orders.total', 2)
            ->assertJsonPath('data.orders.completed', 1)
            ->assertJsonPath('data.ratings.total', 1);
    }

    public function test_dashboard_revenue_sums_only_paid_payments(): void
    {
        $customer = User::factory()->customer()->create();

        $paidOrder = Order::factory()->create(['customer_id' => $customer->id, 'status' => OrderStatus::Paid]);
        $pendingOrder = Order::factory()->create(['customer_id' => $customer->id, 'status' => OrderStatus::Pending]);

        Payment::factory()->paid()->create(['order_id' => $paidOrder->id, 'amount' => 150]);
        Payment::factory()->create(['order_id' => $pendingOrder->id, 'amount' => 999]);

        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.revenue', 150);
    }
}