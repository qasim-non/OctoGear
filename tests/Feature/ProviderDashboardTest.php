<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\StoreStatus;
use App\Enums\UserStatus;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoresCar;
use App\Models\User;
use App\Services\ProviderDashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProviderDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.business_timezone' => 'Asia/Riyadh']);
        $this->travelTo(CarbonImmutable::parse('2026-10-16T10:00:00Z'));
    }

    private function store(User $provider): Store
    {
        return Store::factory()->create(['user_id' => $provider->id, 'status' => StoreStatus::Active]);
    }

    private function direct(Store $store, OrderStatus $status = OrderStatus::Pending, array $attributes = []): Order
    {
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        $part = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);

        return Order::factory()->create([
            'order_type' => OrderType::Specific, 'status' => $status,
            'customer_id' => User::factory()->customer()->create()->id,
            'store_car_component_id' => $part->id, 'requested_unit_price' => 2500,
            'offered_price' => $status === OrderStatus::Pending ? null : 2500,
            'quantity' => 2, ...$attributes,
        ]);
    }

    private function general(Store $store, OrderStatus $status = OrderStatus::AwaitingPayment): Order
    {
        $order = Order::factory()->create([
            'order_type' => OrderType::General, 'status' => $status,
            'customer_id' => User::factory()->customer()->create()->id,
            'component_name' => 'Requested headlight',
        ]);
        $offer = OrderOffer::factory()->create([
            'order_id' => $order->id, 'store_id' => $store->id,
            'price' => 9876, 'status' => $status === OrderStatus::Pending ? OfferStatus::Pending : OfferStatus::Accepted,
        ]);
        if ($status !== OrderStatus::Pending) {
            $order->update(['accepted_offer_id' => $offer->id]);
        }

        return $order->fresh();
    }

    private function payment(Order $order, int $amount, string $updated = '2026-10-16T09:00:00Z', PaymentStatus $status = PaymentStatus::Paid): Payment
    {
        return Payment::factory()->create([
            'order_id' => $order->id, 'amount' => $amount, 'payment_status' => $status,
            'created_at' => '2026-09-01T10:00:00Z', 'updated_at' => $updated,
        ]);
    }

    public function test_empty_dashboard_has_real_zeroes_and_requires_an_active_provider(): void
    {
        $this->getJson('/api/provider/dashboard')->assertUnauthorized();
        $provider = User::factory()->provider()->create();
        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/dashboard')->assertOk()
            ->assertJsonPath('data.stores', [])->assertJsonPath('data.selected_store_id', null)
            ->assertJsonPath('data.period', 'month')->assertJsonPath('data.sales.amount', 0)
            ->assertJsonPath('data.sales.count', 0)->assertJsonPath('data.sales.currency', 'SAR')
            ->assertJsonPath('data.sales.price_scale', 100)
            ->assertJsonPath('data.counts', ['pending_direct_requests' => 0, 'pending_offers' => 0, 'accepted_offers' => 0, 'paid_orders' => 0])
            ->assertJsonPath('data.attention.paid', ['total' => 0, 'items' => []])
            ->assertJsonPath('data.recent_orders', ['total' => 0, 'items' => []]);
        $provider->update(['status' => UserStatus::Blocked]);
        $this->getJson('/api/provider/dashboard')->assertForbidden();
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')->getJson('/api/provider/dashboard')->assertForbidden();
        $this->getJson('/api/provider/dashboard/orders?section=recent')->assertForbidden();
    }

    public function test_summary_distinguishes_pending_accepted_paid_and_completed_across_owned_stores(): void
    {
        $provider = User::factory()->provider()->create();
        $first = $this->store($provider);
        $second = $this->store($provider);
        $pending = $this->direct($first);
        $this->direct($second);
        $this->direct($first, OrderStatus::AwaitingPayment);
        $accepted = $this->general($second);
        $paidDirect = $this->direct($first, OrderStatus::Paid);
        $paidGeneral = $this->general($second, OrderStatus::Paid);
        $completed = $this->direct($first, OrderStatus::Completed);
        $this->payment($paidDirect, 12345);
        $this->payment($paidGeneral, 6789);
        $this->payment($completed, 4000);
        $this->general($first, OrderStatus::Pending);
        // A historical pending offer on a decided order needs no action.
        OrderOffer::factory()->create(['store_id' => $first->id, 'order_id' => $accepted->id, 'status' => OfferStatus::Pending]);
        $otherStore = $this->store(User::factory()->provider()->create());
        $other = $this->general($otherStore, OrderStatus::Paid);
        $this->payment($other, 999999);
        $this->direct($otherStore);

        $response = $this->actingAs($provider, 'sanctum')->getJson('/api/provider/dashboard', ['Accept-Language' => 'en'])->assertOk()
            ->assertJsonCount(2, 'data.stores')
            ->assertJsonPath('data.counts', ['pending_direct_requests' => 2, 'pending_offers' => 1, 'accepted_offers' => 1, 'paid_orders' => 2])
            ->assertJsonPath('data.attention.pending_direct.total', 2)
            ->assertJsonPath('data.attention.awaiting_payment.total', 2)
            ->assertJsonPath('data.attention.paid.total', 2)
            ->assertJsonPath('data.recent_orders.total', 7)
            ->assertJsonPath('data.sales.amount', 23134)->assertJsonPath('data.sales.count', 3);
        $paid = collect($response->json('data.attention.paid.items'))->keyBy('id');
        $this->assertSame(12345, $paid[$paidDirect->id]['total_amount']);
        $this->assertTrue($paid[$paidDirect->id]['can_chat']);
        $this->assertSame($paidDirect->customer->full_name, $paid[$paidDirect->id]['customer_name']);
        $awaiting = collect($response->json('data.attention.awaiting_payment.items'))->keyBy('id');
        $this->assertSame($accepted->accepted_offer_id, $awaiting[$accepted->id]['accepted_offer_id']);
        $this->assertSame(9876, $awaiting[$accepted->id]['total_amount']);
        $this->assertTrue($awaiting[$accepted->id]['can_chat']);
        $pendingSummary = collect($response->json('data.attention.pending_direct.items'))->keyBy('id')[$pending->id];
        $this->assertNull($pendingSummary['customer_name']);
        $this->assertFalse($pendingSummary['can_chat']);
        $this->assertSame(5000, $pendingSummary['total_amount']);
        $this->assertArrayNotHasKey('customer_id', $pendingSummary);
        $this->assertArrayNotHasKey('mobile', $pendingSummary);
        $this->assertNotContains($other->id, array_column($response->json('data.recent_orders.items'), 'id'));
    }

    public function test_store_filter_applies_to_every_section_and_rejects_unowned_or_deleted_stores(): void
    {
        $provider = User::factory()->provider()->create();
        $first = $this->store($provider);
        $second = $this->store($provider);
        $this->direct($first);
        $accepted = $this->general($first);
        $paid = $this->direct($first, OrderStatus::Paid);
        $this->payment($paid, 1000);
        $this->general($first, OrderStatus::Pending);
        $this->direct($second);
        $this->general($second);
        $otherPaid = $this->general($second, OrderStatus::Paid);
        $this->payment($otherPaid, 9000);
        $this->general($second, OrderStatus::Pending);
        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/dashboard?store_id='.$first->id)->assertOk()
            ->assertJsonPath('data.selected_store_id', $first->id)->assertJsonCount(2, 'data.stores')
            ->assertJsonPath('data.counts', ['pending_direct_requests' => 1, 'pending_offers' => 1, 'accepted_offers' => 1, 'paid_orders' => 1])
            ->assertJsonPath('data.attention.awaiting_payment.items.0.id', $accepted->id)
            ->assertJsonPath('data.recent_orders.total', 3)->assertJsonPath('data.sales.amount', 1000);
        $this->getJson('/api/provider/dashboard/orders?section=paid&store_id='.$first->id)->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $paid->id);
        $foreign = $this->store(User::factory()->provider()->create());
        foreach ([$foreign->id, 999999, 'invalid', 0] as $id) {
            $this->getJson('/api/provider/dashboard?store_id='.$id)->assertUnprocessable();
            $this->getJson('/api/provider/dashboard/orders?section=recent&store_id='.$id)->assertUnprocessable();
        }
        $first->delete();
        $this->getJson('/api/provider/dashboard?store_id='.$first->id)->assertUnprocessable();
        $this->getJson('/api/provider/dashboard?period=year')->assertUnprocessable();
        $this->getJson('/api/provider/dashboard/orders')->assertUnprocessable();
        $this->getJson('/api/provider/dashboard/orders?section=other')->assertUnprocessable();
        $this->getJson('/api/provider/dashboard/orders?section=paid&page=0')->assertUnprocessable();
    }

    public function test_recorded_sales_use_successful_payment_time_and_saudi_calendar_boundaries(): void
    {
        $provider = User::factory()->provider()->create();
        $store = $this->store($provider);
        $records = [
            [100, '2026-10-15T21:00:00Z'], [200, '2026-10-15T20:59:59Z'],
            [300, '2026-10-09T21:00:00Z'], [400, '2026-10-09T20:59:59Z'],
            [500, '2026-09-30T21:00:00Z'], [600, '2026-09-30T20:59:59Z'],
            [700, '2026-10-16T10:00:01Z'],
        ];
        foreach ($records as [$amount, $time]) {
            $order = $this->direct($store, OrderStatus::Paid, ['created_at' => '2026-01-01T00:00:00Z']);
            $this->payment($order, $amount, $time);
        }
        foreach ([PaymentStatus::Failed, PaymentStatus::Refunded, PaymentStatus::Pending] as $status) {
            $this->payment($this->direct($store, OrderStatus::Paid), 90000, status: $status);
        }
        $this->payment($this->direct($store, OrderStatus::AwaitingPayment), 90000);
        $this->payment($this->direct($store, OrderStatus::Completed), 1200);
        $this->payment($this->direct($store, OrderStatus::Paid), 90000)->delete();
        // No fabricated revenue for legacy paid rows without payment records.
        $this->direct($store, OrderStatus::Paid, ['offered_price' => 90000]);
        $this->actingAs($provider, 'sanctum');
        foreach ([['today', 1300, 2, '2026-10-15T21:00:00.000000Z'], ['week', 1800, 4, '2026-10-09T21:00:00.000000Z'], ['month', 2700, 6, '2026-09-30T21:00:00.000000Z']] as [$period, $amount, $count, $from]) {
            $this->getJson('/api/provider/dashboard?period='.$period)->assertOk()
                ->assertJsonPath('data.period', $period)->assertJsonPath('data.sales.amount', $amount)
                ->assertJsonPath('data.sales.count', $count)->assertJsonPath('data.sales.from', $from)
                ->assertJsonPath('data.sales.to', '2026-10-16T10:00:00.000000Z')
                ->assertJsonPath('data.sales.timezone', 'Asia/Riyadh');
        }
    }

    public function test_business_timezone_controls_calendar_boundaries_without_changing_utc_storage(): void
    {
        // The alternate timezone and DST case catch hardcoded Saudi time/offsets.
        // Friday and Saturday cases catch an accidental Monday/Sunday week start.
        foreach ([
            ['Asia/Dubai', '2026-10-16T10:00:00Z', 'today', '2026-10-15T20:00:00Z'],
            ['America/New_York', '2026-11-03T12:00:00Z', 'week', '2026-10-31T04:00:00Z'],
            ['Asia/Riyadh', '2026-10-09T20:59:59Z', 'week', '2026-10-02T21:00:00Z'],
            ['Asia/Riyadh', '2026-10-09T21:00:01Z', 'week', '2026-10-09T21:00:00Z'],
        ] as [$timezone, $time, $period, $start]) {
            config(['app.business_timezone' => $timezone]);
            $now = CarbonImmutable::parse($time);
            $from = CarbonImmutable::parse($start);
            $this->travelTo($now);
            $provider = User::factory()->provider()->create();
            $store = $this->store($provider);
            $this->payment($this->direct($store, OrderStatus::Paid), 500, $from->toISOString());
            $this->payment($this->direct($store, OrderStatus::Paid), 9000, $from->subSecond()->toISOString());
            $this->payment($this->direct($store, OrderStatus::Paid), 9000, $now->toISOString());

            $this->actingAs($provider, 'sanctum')->getJson('/api/provider/dashboard?period='.$period)->assertOk()
                ->assertJsonPath('data.sales.timezone', $timezone)
                ->assertJsonPath('data.sales.from', $from->toISOString())
                ->assertJsonPath('data.sales.to', $now->toISOString())
                ->assertJsonPath('data.sales.amount', 500)
                ->assertJsonPath('data.sales.count', 1);

            // New model timestamps still share the existing UTC storage convention.
            $fresh = Payment::factory()->create([
                'order_id' => $this->direct($store, OrderStatus::Paid)->id,
            ]);
            $this->assertSame('UTC', config('app.timezone'));
            $this->assertSame($now->format('Y-m-d H:i:s'), $fresh->getRawOriginal('updated_at'));
            $this->assertSame($now->toISOString(), $fresh->fresh()->updated_at->toISOString());
        }
    }

    public function test_attention_previews_are_bounded_and_drill_down_is_stable_paginated_and_localized(): void
    {
        $provider = User::factory()->provider()->create();
        $store = $this->store($provider);
        $order = $this->direct($store);
        $order->storeCarComponent->component->update(['name_en' => 'Headlight', 'name_ar' => 'مصباح أمامي']);
        Order::factory()->count(16)->create([
            'order_type' => OrderType::Specific, 'status' => OrderStatus::Pending,
            'customer_id' => $order->customer_id, 'store_car_component_id' => $order->store_car_component_id,
            'requested_unit_price' => 2500, 'quantity' => 2,
        ]);
        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/dashboard')->assertOk()
            ->assertJsonPath('data.attention.pending_direct.total', 17)->assertJsonCount(3, 'data.attention.pending_direct.items')
            ->assertJsonPath('data.recent_orders.total', 17)->assertJsonCount(5, 'data.recent_orders.items');
        $first = $this->getJson('/api/provider/dashboard/orders?section=pending_direct', ['Accept-Language' => 'en'])->assertOk()
            ->assertJsonPath('meta.total', 17)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.part_name', 'Headlight')->json('data');
        $second = $this->getJson('/api/provider/dashboard/orders?section=pending_direct&page=2', ['Accept-Language' => 'ar'])->assertOk()
            ->assertJsonPath('data.0.part_name', 'مصباح أمامي')->json('data');
        $ids = array_column([...$first, ...$second], 'id');
        $this->assertCount(17, array_unique($ids));
        $sorted = $ids;
        rsort($sorted);
        $this->assertSame($sorted, $ids);
    }

    public function test_archived_inventory_and_losing_offers_cannot_expose_orders_or_customer_identity(): void
    {
        $provider = User::factory()->provider()->create();
        $store = $this->store($provider);
        $hidden = $this->direct($store, OrderStatus::Paid);
        $this->payment($hidden, 1000);
        $hidden->storeCarComponent->delete();
        $deleted = $this->direct($store);
        $deleted->delete();
        $foreign = $this->general($this->store(User::factory()->provider()->create()));
        OrderOffer::factory()->create(['order_id' => $foreign->id, 'store_id' => $store->id, 'status' => OfferStatus::NotSelected]);
        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/dashboard')->assertOk()
            ->assertJsonPath('data.recent_orders.total', 0)->assertJsonPath('data.sales.amount', 0)
            ->assertJsonPath('data.counts.accepted_offers', 0)->assertJsonPath('data.counts.paid_orders', 0);
        $this->getJson('/api/provider/dashboard/orders?section=recent')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_chat_availability_tracks_customer_eligibility_and_completed_orders_are_history(): void
    {
        $provider = User::factory()->provider()->create();
        $store = $this->store($provider);
        $accepted = $this->general($store);
        $accepted->customer->update(['status' => UserStatus::Blocked]);
        $completed = $this->direct($store, OrderStatus::Completed);
        $paidWithoutRecord = $this->direct($store, OrderStatus::Paid, ['quantity' => 0]);
        $this->actingAs($provider, 'sanctum')->getJson('/api/provider/dashboard')->assertOk()
            ->assertJsonPath('data.attention.awaiting_payment.items.0.can_chat', false)
            ->assertJsonPath('data.attention.paid.total', 1)
            ->assertJsonPath('data.attention.paid.items.0.id', $paidWithoutRecord->id)
            ->assertJsonPath('data.attention.paid.items.0.quantity', null)
            ->assertJsonPath('data.attention.paid.items.0.total_amount', null);
        $rows = $this->getJson('/api/provider/dashboard/orders?section=recent')->assertOk()->json('data');
        $this->assertFalse(collect($rows)->keyBy('id')[$completed->id]['can_chat']);
    }

    public function test_summary_keeps_all_reads_in_a_snapshot_and_preserves_a_callers_transaction(): void
    {
        $provider = User::factory()->provider()->create();
        $store = $this->store($provider);
        $this->direct($store);
        $baseline = DB::transactionLevel();
        $levels = [];
        $capture = true;
        DB::listen(function (QueryExecuted $query) use (&$levels, &$capture): void {
            if ($capture && str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $levels[] = $query->connection->transactionLevel();
            }
        });
        try {
            $summary = app(ProviderDashboardService::class)->summary($provider->id, $store->id, 'month');
        } finally {
            $capture = false;
        }
        $this->assertNotEmpty($levels);
        $this->assertSame([$baseline + 1], array_values(array_unique($levels)));
        $this->assertSame($baseline, DB::transactionLevel());
        $this->assertSame(1, $summary['attention']['pending_direct']['total']);
        $this->assertCount(1, $summary['attention']['pending_direct']['items']);

        // Selection must be rechecked within the snapshot even when an earlier
        // HTTP validation already accepted that store before it was deleted.
        $store->delete();
        try {
            app(ProviderDashboardService::class)->summary($provider->id, $store->id, 'month');
            $this->fail('A missing snapshot store must be rejected.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('store_id', $error->errors());
        }
        $this->assertSame($baseline, DB::transactionLevel());
    }
}
