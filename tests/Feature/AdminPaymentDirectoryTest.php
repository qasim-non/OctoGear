<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Http\Resources\AdminPaymentResource;
use App\Models\Admin;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\User;
use App\Services\AdminPaymentQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdminPaymentDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_allowed_admins_can_read_and_no_payment_state_is_changed(): void
    {
        $payment = Payment::factory()->create(['order_id' => Order::factory()->state(['customer_id' => User::factory()])]);
        $before = $payment->fresh()->getAttributes();
        $this->getJson('/api/admin/payments')->assertUnauthorized();
        foreach ([User::factory()->customer()->create(), User::factory()->provider()->create(), Admin::factory()->blocked()->create(), Admin::factory()->create(['status' => 'inactive'])] as $actor) {
            $this->app['auth']->forgetGuards();
            $this->withToken($actor->createToken('test')->plainTextToken)->getJson('/api/admin/payments')->assertForbidden();
            $this->assertFalse(Gate::forUser($actor)->allows('viewAdminDirectory', Payment::class));
        }
        foreach (['active'] as $status) {
            $this->app['auth']->forgetGuards();
            $admin = Admin::factory()->create(['status' => $status]);
            $this->withToken($admin->createToken('admin', ['admin'])->plainTextToken)->getJson('/api/admin/payments')
                ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('data.0.id', $payment->id);
        }
        $this->assertSame($before, $payment->fresh()->getAttributes());
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_payment_attempts_are_separate_ordered_rows_with_recorded_minor_unit_amounts(): void
    {
        $order = Order::factory()->create(['customer_id' => User::factory(), 'offered_price' => 99999]);
        $payments = collect(PaymentStatus::cases())->map(fn ($status) => Payment::factory()->create([
            'order_id' => $order->id, 'payment_status' => $status, 'amount' => 12345, 'created_at' => '2026-10-21 12:00:00',
        ]));
        $payments->first()->delete();
        $this->actingAs(Admin::factory()->create(), 'admin')->getJson('/api/admin/payments?order_id='.$order->id)
            ->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0.id', $payments->last()->id)->assertJsonPath('data.0.amount', 12345)
            ->assertJsonPath('data.0.currency', 'SAR')->assertJsonPath('data.0.price_scale', 100)
            ->assertJsonPath('data.3.archived', true)->assertJsonMissingPath('data.0.commission');
        foreach (PaymentStatus::cases() as $status) {
            $this->getJson('/api/admin/payments?status='.$status->value)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.payment_status', $status->value);
        }
    }

    public function test_filters_combine_and_date_bounds_apply_to_payment_creation_not_order_creation(): void
    {
        $customer = User::factory()->create(['full_name' => 'Customer 0']);
        $order = Order::factory()->create(['customer_id' => $customer->id, 'created_at' => '2025-01-01']);
        $store = Store::factory()->create(['name' => 'Store 0']);
        $offer = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $store->id]);
        $order->update(['accepted_offer_id' => $offer->id]);
        $inside = Payment::factory()->paid()->create(['order_id' => $order->id, 'payment_method' => 'credit_card', 'created_at' => '2026-10-21 23:59:59']);
        Payment::factory()->paid()->create(['order_id' => $order->id, 'payment_method' => 'credit_card', 'created_at' => '2026-10-22 00:00:00']);
        Payment::factory()->create(['order_id' => $order->id, 'created_at' => '2026-10-21 00:00:00']);
        $filters = ['search' => '0', 'order_id' => $order->id, 'city_id' => $customer->city_id, 'type' => 'general', 'status' => 'paid', 'method' => 'credit_card', 'date_from' => '2026-10-21', 'date_to' => '2026-10-21'];
        $this->actingAs(Admin::factory()->create(), 'admin')->getJson('/api/admin/payments?'.http_build_query($filters))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $inside->id);
        $this->getJson('/api/admin/payments?date_to=2026-10-21')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/payments?date_from=2026-10-22')->assertOk()->assertJsonCount(1, 'data');
        foreach ([$customer->mobile, $store->name] as $search) {
            $this->getJson('/api/admin/payments?'.http_build_query(['search' => $search, 'status' => 'paid']))->assertOk()->assertJsonCount(2, 'data');
        }
        $this->getJson('/api/admin/payments?'.http_build_query(['search' => $store->name, 'method' => 'cash', 'status' => 'paid']))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_historical_specific_context_is_localized_searchable_and_serializes_without_queries(): void
    {
        $part = StoreCarComponent::factory()->create();
        $part->component->update(['name_en' => 'Mirror', 'name_ar' => 'مرآة']);
        $customer = User::factory()->create();
        $order = Order::factory()->specific()->create(['customer_id' => $customer->id, 'store_car_component_id' => $part->id]);
        Payment::factory()->paid()->create(['order_id' => $order->id]);
        $store = $part->storeCar->store;
        $store->delete();
        $part->storeCar->delete();
        $part->component->delete();
        $part->delete();
        $order->delete();
        $this->actingAs(Admin::factory()->create(), 'admin');
        foreach (['en' => 'Mirror', 'ar' => 'مرآة'] as $locale => $label) {
            $this->getJson('/api/admin/payments?'.http_build_query(['search' => $store->name]), ['Accept-Language' => $locale])
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.order.part_name', $label)
                ->assertJsonPath('data.0.order.available', false)->assertJsonPath('data.0.order.customer.name', $customer->full_name)
                ->assertJsonPath('data.0.order.store.name', $store->name)->assertJsonMissingPath('data.0.order.customer.password');
        }
        $prepared = app(AdminPaymentQueryService::class)->index([]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        AdminPaymentResource::collection($prepared->getCollection())->response()->getData(true);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_deleted_general_context_and_missing_order_are_retained_without_fabrication(): void
    {
        $order = Order::factory()->create(['customer_id' => User::factory(), 'component_name' => 'Custom part']);
        $store = Store::factory()->create();
        $offer = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $store->id]);
        $order->update(['accepted_offer_id' => $offer->id]);
        $payment = Payment::factory()->create(['order_id' => $order->id]);
        $offer->delete();
        $store->delete();
        $order->delete();
        $this->actingAs(Admin::factory()->create(), 'admin')->getJson('/api/admin/payments?'.http_build_query(['search' => $store->name]))
            ->assertOk()->assertJsonPath('data.0.order.part_name', 'Custom part')->assertJsonPath('data.0.order.store.id', $store->id);
        $prepared = app(AdminPaymentQueryService::class)->index([])->first();
        $prepared->setRelation('order', null);
        $data = (new AdminPaymentResource($prepared))->response()->getData(true)['data'];
        $this->assertNull($data['order']);
        $this->assertSame($payment->amount, $data['amount']);
    }

    public function test_invalid_filters_are_localized_and_pagination_is_stable_at_twenty_five(): void
    {
        $city = City::factory()->create();
        $city->delete();
        $this->actingAs(Admin::factory()->create(), 'admin');
        foreach (['en', 'ar'] as $locale) {
            foreach (['status=completed', 'method=bitcoin', 'type=invalid', 'page=0', 'order_id=-1', 'search[]=bad', 'city_id='.$city->id, 'date_from=2026-02-30', 'date_from=2026-10-21&date_to=2026-10-20'] as $query) {
                $response = $this->getJson('/api/admin/payments?'.$query, ['Accept-Language' => $locale])->assertUnprocessable()->assertJsonPath('success', false);
                $this->assertSame(__('admin_payments.filters_invalid', [], $locale), collect($response->json('errors'))->flatten()->first());
            }
        }
        $order = Order::factory()->create(['customer_id' => User::factory()]);
        $rows = Payment::factory()->count(26)->create(['order_id' => $order->id, 'created_at' => '2026-10-21 12:00:00']);
        $this->getJson('/api/admin/payments')->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('data.0.id', $rows->last()->id)->assertJsonPath('meta.per_page', 25);
        $this->getJson('/api/admin/payments?page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $rows->first()->id);
        $this->getJson('/api/admin/payments?page=3')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.last_page', 2);
    }
}
