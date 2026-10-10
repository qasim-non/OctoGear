<?php

namespace App\Repositories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class MessageRepository
{
    public const TIMELINE_PAGE_SIZE = 40;

    /** @return Collection<int, Message> */
    public function timeline(Conversation $conversation, ?int $beforeId, ?int $afterId): Collection
    {
        return $conversation->messages()
            ->when($beforeId !== null, fn ($query) => $query->where('id', '<', $beforeId))
            ->when(
                $afterId !== null,
                fn ($query) => $query->where('id', '>', $afterId)->orderBy('id'),
                fn ($query) => $query->orderByDesc('id'),
            )
            ->limit(self::TIMELINE_PAGE_SIZE + 1)
            ->get();
    }

    public function paginate(Conversation $conversation, int $page): LengthAwarePaginator
    {
        return $conversation->messages()->with('sender')->latest()->paginate(20, ['*'], 'page', $page);
    }

    public function contains(Conversation $conversation, int $messageId): bool
    {
        return $conversation->messages()->whereKey($messageId)->exists();
    }

    public function readThroughForSender(Conversation $conversation, User $sender): int
    {
        return (int) $conversation->messages()->where('sender_id', $sender->id)
            ->where('is_read', true)->max('id');
    }

    public function markIncomingReadThrough(Conversation $conversation, User $reader, int $throughId): int
    {
        return $conversation->messages()
            ->where('id', '<=', $throughId)
            ->where('sender_id', '!=', $reader->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /** Serialize a sender's retry keys across conversations inside a transaction. */
    public function lockSender(User $sender): void
    {
        User::query()->whereKey($sender->id)->lockForUpdate()->firstOrFail();
    }

    public function findRetry(User $sender, string $key): ?Message
    {
        return Message::withTrashed()->where('sender_id', $sender->id)
            ->where('client_message_id', $key)->first();
    }

    public function create(Conversation $conversation, User $sender, string $content, ?string $key): Message
    {
        return $conversation->messages()->create([
            'content' => $content,
            'sender_id' => $sender->id,
            'client_message_id' => $key,
            'is_read' => false,
        ])->setRelation('conversation', $conversation);
    }
}
