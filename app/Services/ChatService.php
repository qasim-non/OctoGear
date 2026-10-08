<?php

namespace App\Services;

use App\Data\Chat\ConversationData;
use App\Data\Chat\MessageTimeline;
use App\Data\Chat\OfferConversationData;
use App\Events\MessageSent;
use App\Exceptions\BusinessRuleException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OrderOffer;
use App\Models\User;
use App\Repositories\ConversationRepository;
use App\Repositories\MessageRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ChatService
{
    public function __construct(
        private ConversationRepository $conversations,
        private MessageRepository $messages,
        private ChatEligibility $eligibility,
    ) {}

    public function details(Conversation $conversation, User $viewer): ConversationData
    {
        return $this->present($this->conversations->loadForDisplay($conversation, $viewer));
    }

    public function offerConversation(OrderOffer $offer, User $viewer): OfferConversationData
    {
        $offer = $this->conversations->loadOfferContext($offer);
        $conversation = $this->conversations->findForOffer($offer);
        $details = $conversation ? $this->details($conversation, $viewer) : null;

        return new OfferConversationData(
            $offer,
            $details,
            $details?->canSend ?? $this->eligibility->canStart($offer),
        );
    }

    public function inbox(User $viewer, bool $withMessages, int $page): LengthAwarePaginator
    {
        return $this->conversations->paginateFor($viewer, $withMessages, $page)
            ->through(fn (Conversation $conversation) => $this->present($conversation));
    }

    public function createDirect(User $user, array $data): ConversationData
    {
        $customerId = $user->isProvider() ? (int) $data['customer_id'] : $user->id;
        $providerId = $user->isProvider() ? $user->id : (int) $data['provider_id'];
        $conversation = $this->conversations->firstOrCreateDirect($customerId, $providerId);

        // Preserve the legacy creation response, which has no latest-message field.
        return $this->present($this->conversations->loadForDisplay($conversation, $user, withActivity: false));
    }

    public function timeline(Conversation $conversation, ?int $beforeId, ?int $afterId): MessageTimeline
    {
        $messages = $this->messages->timeline($conversation, $beforeId, $afterId);

        // Forward paging selects the earliest next page before displaying newest first.
        return new MessageTimeline(
            $messages->take(MessageRepository::TIMELINE_PAGE_SIZE)->sortByDesc('id')->values(),
            $messages->count() > MessageRepository::TIMELINE_PAGE_SIZE,
        );
    }

    public function messages(Conversation $conversation, int $page): LengthAwarePaginator
    {
        return $this->messages->paginate($conversation, $page);
    }

    public function markRead(Conversation $conversation, User $reader, int $throughId): void
    {
        Gate::forUser($reader)->authorize('view', $conversation);
        if (! $this->messages->contains($conversation, $throughId)) {
            throw new BusinessRuleException(
                messageKey: 'chat.invalid_read_boundary',
                statusCode: 422,
                errors: ['through_id' => [__('chat.invalid_read_boundary')]],
            );
        }
        $this->messages->markIncomingReadThrough($conversation, $reader, $throughId);
    }

    public function start(OrderOffer $offer, User $sender, array $data): Message
    {
        return DB::transaction(function () use ($offer, $sender, $data) {
            // Serialize first sends even before the conversation exists.
            $locked = $this->conversations->lockOffer($offer->id);
            Gate::forUser($sender)->authorize('startConversation', $locked);
            $store = $locked->store;
            if (! $this->eligibility->storeAllowsChat($store, $store?->user_id)) {
                throw new BusinessRuleException(messageKey: 'chat.unavailable', statusCode: 403);
            }
            $conversation = $this->conversations->firstOrCreateForOffer($locked, $sender->id, $store->user_id);

            return $this->send($conversation, $sender, $data);
        }, 3);
    }

    public function send(Conversation $conversation, User $sender, array $data): Message
    {
        return DB::transaction(function () use ($conversation, $sender, $data) {
            // Sender lock also serializes a retry key used on different chats.
            $this->messages->lockSender($sender);
            $chat = $this->conversations->lockConversation($conversation->id);
            Gate::forUser($sender)->authorize('view', $chat);
            $key = $data['client_message_id'] ?? null;
            if ($key) {
                $existing = $this->messages->findRetry($sender, $key);
                if ($existing) {
                    if ($existing->trashed() || $existing->conversation_id !== $chat->id
                        || $existing->content !== $data['content']) {
                        throw new BusinessRuleException(messageKey: 'chat.retry_conflict', statusCode: 409);
                    }

                    return $existing->setRelation('conversation', $chat);
                }
            }
            if (! $this->eligibility->canSend($chat)) {
                throw new BusinessRuleException(messageKey: 'chat.unavailable', statusCode: 403);
            }
            $message = $this->messages->create($chat, $sender, $data['content'], $key);
            $this->conversations->touch($chat);
            // The existing notification is committed once alongside the message.
            MessageSent::dispatch($message);

            return $message;
        }, 3);
    }

    private function present(Conversation $conversation): ConversationData
    {
        return new ConversationData($conversation, $this->eligibility->canSend($conversation));
    }
}
