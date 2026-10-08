<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserStatus;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\FirstConversationMessageResource;
use App\Http\Resources\OfferConversationResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationRefactorTest extends TestCase
{
    use RefreshDatabase;

    private function offerContext(): array
    {
        $customer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $order = Order::factory()->create(['customer_id' => $customer->id]);
        $offer = OrderOffer::factory()->create(['order_id' => $order->id, 'store_id' => $store->id]);
        Sanctum::actingAs($customer);

        return [$customer, $provider, $store, $order, $offer,
            "/api/customer/orders/{$order->id}/offers/{$offer->id}/conversation"];
    }

    private function directConversation(): array
    {
        $customer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();
        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id, 'provider_id' => $provider->id,
        ]);
        Sanctum::actingAs($customer);

        return [$customer, $provider, $conversation];
    }

    public function test_first_send_requires_uuid_in_the_standard_localized_validation_envelope(): void
    {
        [, , , , , $url] = $this->offerContext();
        foreach (['en', 'ar'] as $locale) {
            $this->withHeader('Accept-Language', $locale)
                ->postJson("$url/messages", ['content' => 'Hello'])
                ->assertUnprocessable()->assertJsonPath('success', false)
                ->assertJsonPath('message', trans('auth.general.validation_failed', [], $locale))
                ->assertJsonValidationErrors('client_message_id');
        }
        $this->postJson("$url/messages", ['content' => 'Hello', 'client_message_id' => 'bad'])
            ->assertUnprocessable()->assertJsonValidationErrors('client_message_id');
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_legacy_send_keeps_optional_uuid_and_trims_content(): void
    {
        [, , $conversation] = $this->directConversation();
        $this->postJson("/api/conversations/{$conversation->id}/messages", ['content' => '  Hello  '])
            ->assertCreated()->assertJsonPath('data.content', 'Hello')
            ->assertJsonPath('data.client_message_id', null);
    }

    public function test_cursor_and_read_input_rules_use_form_requests(): void
    {
        [, , $conversation] = $this->directConversation();
        $url = "/api/conversations/{$conversation->id}";
        foreach ([
            ['before_id=1&after_id=0', 'before_id'],
            ['before_id=0', 'before_id'],
            ['after_id=-1', 'after_id'],
            ['after_id=abc', 'after_id'],
        ] as [$query, $field]) {
            $this->getJson("$url/timeline?$query")->assertUnprocessable()
                ->assertJsonPath('success', false)->assertJsonValidationErrors($field);
        }
        $this->getJson("$url/timeline?after_id=0")->assertOk()
            ->assertJsonPath('data.messages', [])->assertJsonPath('data.has_more', false);
        foreach ([[], ['through_id' => 0], ['through_id' => 'abc']] as $payload) {
            $this->patchJson("$url/read", $payload)->assertUnprocessable()
                ->assertJsonPath('success', false)->assertJsonValidationErrors('through_id');
        }
    }

    public function test_authorization_precedes_timeline_read_and_first_send_validation(): void
    {
        [, , $conversation] = $this->directConversation();
        Sanctum::actingAs(User::factory()->customer()->create());
        $this->getJson("/api/conversations/{$conversation->id}/timeline?after_id=-1")->assertForbidden();
        $this->patchJson("/api/conversations/{$conversation->id}/read", [])->assertForbidden();
        $this->postJson("/api/conversations/{$conversation->id}/messages", [])->assertForbidden();

        [$customer, , , , $offer, $url] = $this->offerContext();
        $otherOrder = Order::factory()->create(['customer_id' => $customer->id]);
        $this->postJson("/api/customer/orders/{$otherOrder->id}/offers/{$offer->id}/conversation/messages", [])
            ->assertNotFound();
        Sanctum::actingAs(User::factory()->customer()->create());
        $this->postJson("$url/messages", [])->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_read_boundary_is_scoped_and_only_marks_incoming_messages_through_it(): void
    {
        [$customer, $provider, $conversation] = $this->directConversation();
        $incoming = Message::factory()->create([
            'conversation_id' => $conversation->id, 'sender_id' => $provider->id, 'is_read' => false,
        ]);
        $own = Message::factory()->create([
            'conversation_id' => $conversation->id, 'sender_id' => $customer->id, 'is_read' => false,
        ]);
        $later = Message::factory()->create([
            'conversation_id' => $conversation->id, 'sender_id' => $provider->id, 'is_read' => false,
        ]);
        $otherConversation = Conversation::factory()->create([
            'customer_id' => User::factory()->customer()->create()->id,
            'provider_id' => $provider->id,
        ]);
        $foreign = Message::factory()->create([
            'conversation_id' => $otherConversation->id, 'sender_id' => $provider->id,
        ]);
        $url = "/api/conversations/{$conversation->id}/read";
        $this->patchJson($url, ['through_id' => $foreign->id])->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonValidationErrors('through_id');
        $this->assertFalse($incoming->fresh()->is_read);
        $this->patchJson($url, ['through_id' => $own->id])->assertOk()->assertJsonPath('data.through_id', $own->id);
        $this->patchJson($url, ['through_id' => $own->id])->assertOk();
        $this->assertTrue($incoming->fresh()->is_read);
        $this->assertFalse($own->fresh()->is_read);
        $this->assertFalse($later->fresh()->is_read);
        $later->delete();
        $this->patchJson($url, ['through_id' => $later->id])->assertUnprocessable();
    }

    public function test_preview_eligibility_matches_send_rules_without_creating_a_conversation(): void
    {
        [, $provider, $store, , , $url] = $this->offerContext();
        $this->getJson($url)->assertOk()->assertJsonPath('data.can_send', true)
            ->assertJsonPath('data.conversation', null);
        $provider->update(['status' => UserStatus::Blocked]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.can_send', false);
        $provider->update(['status' => UserStatus::Unblocked]);
        $store->update(['status' => StoreStatus::Inactive]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.can_send', false);
        $this->postJson("$url/messages", ['content' => 'Hello', 'client_message_id' => (string) Str::uuid()])
            ->assertForbidden()->assertJsonPath('success', false);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_existing_chat_keeps_its_participants_when_store_ownership_changes(): void
    {
        [, , $store, , , $url] = $this->offerContext();
        $id = $this->postJson("$url/messages", ['content' => 'Hello', 'client_message_id' => (string) Str::uuid()])
            ->assertOk()->json('data.conversation.id');
        $store->update(['user_id' => User::factory()->provider()->create()->id]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.can_send', false)
            ->assertJsonPath('data.conversation.can_send', false);
        $this->getJson("/api/conversations/$id")->assertOk()->assertJsonPath('data.can_send', false);
        $this->postJson("/api/conversations/$id/messages", ['content' => 'New'])->assertForbidden();
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_identical_retry_replays_before_new_send_eligibility_and_deleted_retry_conflicts(): void
    {
        [, , $store, , , $url] = $this->offerContext();
        $payload = ['content' => 'Hello', 'client_message_id' => (string) Str::uuid()];
        $first = $this->postJson("$url/messages", $payload)->assertOk();
        $id = $first->json('data.conversation.id');
        $messageId = $first->json('data.message.id');
        $store->update(['status' => StoreStatus::Inactive]);
        $this->postJson("/api/conversations/$id/messages", $payload)->assertCreated()
            ->assertJsonPath('data.id', $messageId);
        $this->assertDatabaseCount('notifications', 1);
        Message::findOrFail($messageId)->delete();
        $this->postJson("/api/conversations/$id/messages", $payload)->assertConflict();
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_prepared_resources_serialize_without_database_queries(): void
    {
        [$customer, , , , $offer] = $this->offerContext();
        $chat = app(ChatService::class);
        $payload = ['content' => 'Hello', 'client_message_id' => (string) Str::uuid()];
        $message = $chat->start($offer, $customer, $payload);
        $this->assertTrue($message->relationLoaded('conversation'));
        $retry = $chat->start($offer, $customer, $payload);
        $this->assertTrue($retry->relationLoaded('conversation'));
        $id = $message->conversation_id;
        $details = $chat->details(Conversation::findOrFail($id), $customer);
        $preview = $chat->offerConversation($offer, $customer);
        $inbox = $chat->inbox($customer, true, 1);
        $request = Request::create('/api/conversations');
        $request->setUserResolver(fn () => $customer);
        $originalRequest = app('request');
        app()->instance('request', $request);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $resources = [
                new ConversationResource($details),
                new OfferConversationResource($preview),
                new ConversationResource($inbox->items()[0]),
            ];
            foreach ($resources as $resource) {
                $data = $resource->response($request)->getData(true)['data'];
                $this->assertTrue($data['can_send']);
            }
            $firstMessage = (new FirstConversationMessageResource($retry, $details))->response($request)->getData(true);
            $this->assertSame($message->id, $firstMessage['data']['message']['id']);
            $this->assertTrue($firstMessage['data']['conversation']['can_send']);
            $this->assertSame([], DB::getQueryLog(), 'Serialization must not issue database queries.');
        } finally {
            DB::disableQueryLog();
            app()->instance('request', $originalRequest);
        }
    }

    public function test_inbox_boolean_spellings_and_page_validation_remain_consistent(): void
    {
        [, , $conversation] = $this->directConversation();
        foreach (['true', '1', 'on', 'yes'] as $value) {
            $this->getJson("/api/conversations?with_messages=$value")->assertOk()->assertJsonPath('meta.total', 0);
        }
        foreach (['false', '0', 'off', 'no'] as $value) {
            $this->getJson("/api/conversations?with_messages=$value")->assertOk()->assertJsonPath('meta.total', 1);
        }
        $this->getJson('/api/conversations?with_messages=invalid')->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonValidationErrors('with_messages');
        $this->getJson('/api/conversations?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson("/api/conversations/{$conversation->id}/messages?page=abc")->assertUnprocessable()
            ->assertJsonValidationErrors('page');
    }

    public function test_direct_creation_does_not_reuse_an_offer_conversation_for_the_same_participants(): void
    {
        [, $provider, , , , $url] = $this->offerContext();
        $offerId = $this->postJson("$url/messages", ['content' => 'Offer', 'client_message_id' => (string) Str::uuid()])
            ->assertOk()->json('data.conversation.id');
        $direct = $this->postJson('/api/conversations', ['provider_id' => $provider->id])->assertOk()
            ->assertJsonPath('data.offer_id', null)->assertJsonPath('data.store', null)
            ->assertJsonPath('data.can_send', true)->assertJsonMissingPath('data.latest_message');
        $this->assertNotSame($offerId, $direct->json('data.id'));
        $this->postJson('/api/conversations', ['provider_id' => $provider->id])->assertOk()
            ->assertJsonPath('data.id', $direct->json('data.id'));
        $this->assertDatabaseCount('conversations', 2);
    }

    public function test_inbox_latest_messages_and_unread_counts_are_scoped_per_conversation(): void
    {
        [$customer, $provider, $first] = $this->directConversation();
        $otherCustomer = User::factory()->customer()->create();
        $second = Conversation::factory()->create([
            'customer_id' => $otherCustomer->id, 'provider_id' => $provider->id,
        ]);
        Message::factory()->count(2)->create([
            'conversation_id' => $first->id, 'sender_id' => $customer->id, 'is_read' => false,
        ]);
        Message::factory()->create([
            'conversation_id' => $first->id, 'sender_id' => $provider->id,
            'is_read' => false, 'content' => 'Provider reply',
        ]);
        Message::factory()->create([
            'conversation_id' => $second->id, 'sender_id' => $otherCustomer->id,
            'is_read' => false, 'content' => 'Second conversation',
        ]);
        Sanctum::actingAs($provider);
        $this->getJson('/api/conversations?with_messages=true')->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.0.latest_message.content', 'Second conversation')
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonPath('data.1.unread_count', 2)
            ->assertJsonPath('data.1.latest_message.content', 'Provider reply');
    }

    public function test_forward_timeline_pages_do_not_skip_messages_and_end_at_the_correct_boundary(): void
    {
        [, $provider, $conversation] = $this->directConversation();
        $messages = Message::factory()->count(81)->create([
            'conversation_id' => $conversation->id, 'sender_id' => $provider->id,
        ]);
        $cursor = 0;
        $seen = [];
        foreach ([40, 40, 1] as $index => $expectedCount) {
            $page = $this->getJson("/api/conversations/{$conversation->id}/timeline?after_id=$cursor")
                ->assertOk()->assertJsonCount($expectedCount, 'data.messages')
                ->assertJsonPath('data.has_more', $index < 2);
            $ids = array_column($page->json('data.messages'), 'id');
            $descending = $ids;
            rsort($descending);
            $this->assertSame($descending, $ids);
            $seen = [...$seen, ...$ids];
            $cursor = max($ids);
        }
        sort($seen);
        $this->assertSame($messages->pluck('id')->all(), $seen);
        $this->getJson("/api/conversations/{$conversation->id}/timeline?after_id=$cursor")
            ->assertOk()->assertJsonPath('data.messages', [])->assertJsonPath('data.has_more', false);
    }
}
