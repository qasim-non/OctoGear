<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProviderDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_demo_is_browsable_actionable_and_has_contextual_chats(): void
    {
        config(['images.disk' => 'provider_demo_test', 'customer_car_media.disk' => 'provider_demo_test']);
        Storage::fake('provider_demo_test');
        $this->withoutMiddleware(ThrottleRequests::class);
        try {
            $this->seed(DemoDataSeeder::class);
            $provider = User::where('mobile', '+966500000200')->firstOrFail();
            $this->actingAs($provider, 'sanctum');

            $first = $this->getJson('/api/provider/orders/general')->assertOk()->json('data');
            $this->assertCount(15, $first);
            $this->assertCount(15, array_unique(array_column(array_column($first, 'city'), 'id')));
            foreach ($first as $row) {
                $this->assertNotEmpty($row['images']);
                $this->assertNotEmpty($row['vehicle_details']);
            }
            $this->getJson($first[0]['images'][0]['url'])->assertOk();
            foreach (DB::table('users')->whereBetween('mobile', ['+966500000500', '+966500000533'])->get() as $customer) {
                $rows = $this->getJson('/api/provider/orders/general?city_id='.$customer->city_id)->assertOk()->json('data');
                $this->assertGreaterThanOrEqual(3, count($rows));
                foreach ($rows as $row) {
                    $this->assertSame($customer->city_id, $row['city']['id']);
                }
            }

            foreach (OrderStatus::cases() as $status) {
                $rows = $this->getJson('/api/provider/orders/specific?status='.$status->value)->assertOk()->json('data');
                $this->assertGreaterThanOrEqual(5, count($rows));
                foreach ($rows as $row) {
                    $this->assertSame($status->value, $row['status']);
                }
            }
            $pending = Order::where('notes', 'like', '[PROVIDER-DEMO:direct-pending-%')->firstOrFail();
            $this->getJson('/api/provider/orders/'.$pending->id)->assertOk()
                ->assertJsonPath('data.can_accept', true)->assertJsonPath('data.can_reject', true);
            $this->postJson('/api/provider/orders/'.$pending->id.'/accept', [
                'expected_unit_price' => $pending->requested_unit_price, 'expected_quantity' => $pending->quantity,
            ])->assertOk()->assertJsonPath('data.status', 'awaiting_payment');

            foreach (OfferStatus::cases() as $status) {
                $offer = OrderOffer::where('status', $status)->whereHas('store', fn ($q) => $q->where('user_id', $provider->id))
                    ->whereHas('order', fn ($q) => $q->where('notes', 'like', '[PROVIDER-DEMO:%'))->firstOrFail();
                $this->getJson('/api/provider/orders/offers/'.$offer->id)->assertOk()
                    ->assertJsonPath('data.status', $status->value)->assertJsonCount(1, 'data.images');
            }
            foreach (['paid', 'completed'] as $status) {
                $this->getJson('/api/provider/orders/paid?status='.$status)->assertOk();
                $order = Order::where('notes', 'like', '[PROVIDER-DEMO:direct-'.$status.'-%')->firstOrFail();
                $this->getJson('/api/provider/orders/paid/'.$order->id)->assertOk()
                    ->assertJsonPath('data.gross_amount', $order->payment->amount);
            }
            $this->getJson('/api/provider/dashboard')->assertOk();
            $this->getJson('/api/conversations?with_messages=true')->assertOk();
            foreach (['direct-awaiting_payment', 'direct-paid', 'direct-completed', 'offer-2', 'offer-3', 'offer-4'] as $key) {
                $order = Order::where('notes', 'like', '[PROVIDER-DEMO:'.$key.'-%')->firstOrFail();
                $chat = Conversation::where($order->isGeneral() ? 'offer_id' : 'order_id', $order->isGeneral() ? $order->accepted_offer_id : $order->id)->firstOrFail();
                $this->getJson('/api/provider/orders/'.$order->id.'/conversation')->assertOk()
                    ->assertJsonPath('data.conversation.id', $chat->id);
                $this->getJson('/api/conversations/'.$chat->id.'/timeline')->assertOk()->assertJsonCount(4, 'data.messages');
            }
            $this->assertGreaterThan(0, DB::table('messages')->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
                ->where('conversations.provider_id', $provider->id)->where('sender_id', '!=', $provider->id)->where('is_read', false)->count());
        } finally {
            Storage::disk('provider_demo_test')->deleteDirectory('demo');
        }
    }
}
