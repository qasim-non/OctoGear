<?php

namespace App\Services;

use App\Enums\SupportActor;
use App\Enums\SupportStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\BroadcastSupportUpdate;
use App\Models\Admin;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\SupportReplyNotification;
use App\Repositories\SupportConversationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SupportChatService
{
    public function __construct(private SupportConversationRepository $conversations) {}

    public function send(User|Admin $actor, array $input, ?SupportConversation $existing = null): array
    {
        return DB::transaction(function () use ($actor, $input, $existing) {
            // Serialize first creation on the account; serialize administrator retries on their actor.
            $actor = $actor->newQuery()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('access', SupportConversation::class);
            if ($existing) {
                Gate::forUser($actor)->authorize('view', $existing);
            }
            $kind = $actor instanceof Admin ? SupportActor::Admin : SupportActor::User;
            $retry = SupportMessage::where('sender_kind', $kind)->where('sender_id', $actor->getKey())
                ->where('client_message_id', $input['client_message_id'])->first();
            if ($retry) {
                $conversation = SupportConversation::findOrFail($retry->support_conversation_id);
                Gate::forUser($actor)->authorize('view', $conversation);
                if ($retry->content !== $input['content'] || ($existing && $existing->id !== $conversation->id)) {
                    $this->conflict();
                }

                return ['conversation' => $this->conversations->summary($conversation->id), 'message' => $retry];
            }
            $conversation = $existing
                ? SupportConversation::whereKey($existing->id)->lockForUpdate()->firstOrFail()
                : SupportConversation::where('user_id', $actor->id)->lockForUpdate()->first();
            if (! $conversation) {
                abort_unless($actor instanceof User, 403);
                $conversation = SupportConversation::create(['user_id' => $actor->id]);
                $conversation->refresh();
            }
            Gate::forUser($actor)->authorize('view', $conversation);
            if ($conversation->status !== SupportStatus::Open) {
                throw new BusinessRuleException(messageKey: 'support.closed', statusCode: 409);
            }
            $owner = User::findOrFail($conversation->user_id);
            Gate::forUser($owner)->authorize('access', SupportConversation::class);
            $message = $conversation->messages()->create(['sender_kind' => $kind, 'sender_id' => $actor->getKey(),
                'content' => $input['content'], 'client_message_id' => $input['client_message_id']]);
            $conversation->forceFill(['last_message_id' => $message->id, 'last_activity_at' => now(), 'version' => $conversation->version + 1])->save();
            if ($actor instanceof Admin) {
                $owner->notify(new SupportReplyNotification($message));
            }
            $this->broadcast($conversation->id, 'message', $message->id);

            return ['conversation' => $this->conversations->summary($conversation->id), 'message' => $message];
        }, 3);
    }

    public function read(User|Admin $actor, SupportConversation $conversation, int $through): SupportConversation
    {
        return DB::transaction(function () use ($actor, $conversation, $through) {
            $conversation = SupportConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('view', $conversation);
            if (! $conversation->messages()->whereKey($through)->exists()) {
                throw new BusinessRuleException(messageKey: 'support.invalid_boundary', statusCode: 422);
            }
            $field = $actor instanceof Admin ? 'support_read_through_id' : 'user_read_through_id';
            if ($through > $conversation->$field) {
                $conversation->forceFill([$field => $through, 'version' => $conversation->version + 1])->save();
                $this->broadcast($conversation->id, 'read');
            }

            return $this->conversations->summary($conversation->id);
        });
    }

    public function status(User|Admin $actor, SupportConversation $conversation, SupportStatus $status, int $version): SupportConversation
    {
        return DB::transaction(function () use ($actor, $conversation, $status, $version) {
            $conversation = SupportConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('view', $conversation);
            abort_if($actor instanceof User && $status !== SupportStatus::Open, 403);
            if ($conversation->version !== $version) {
                $this->conflict();
            }
            if ($conversation->status === $status) {
                return $this->conversations->summary($conversation->id);
            }
            DB::table('support_conversation_events')->insert(['support_conversation_id' => $conversation->id,
                'actor_kind' => $actor instanceof Admin ? 'admin' : 'user', 'actor_id' => $actor->getKey(),
                'from_status' => $conversation->status->value, 'to_status' => $status->value, 'created_at' => now()]);
            $conversation->forceFill(['status' => $status, 'version' => $conversation->version + 1,
                'closed_at' => $status === SupportStatus::Closed ? now() : null,
                'closed_by' => $status === SupportStatus::Closed ? $actor->getKey() : null,
                'reopened_at' => $status === SupportStatus::Open ? now() : $conversation->reopened_at,
                'last_activity_at' => now()])->save();
            $this->broadcast($conversation->id, 'status');

            return $this->conversations->summary($conversation->id);
        });
    }

    private function conflict(): never
    {
        throw new BusinessRuleException(messageKey: 'support.conflict', statusCode: 409);
    }

    private function broadcast(int $id, string $kind, ?int $message = null): void
    {
        if (config('chat_realtime.enabled')) {
            dispatch((new BroadcastSupportUpdate($id, $kind, $message))
                ->onConnection(config('chat_realtime.queue_connection'))->onQueue(config('chat_realtime.queue'))->afterCommit());
        }
    }
}
