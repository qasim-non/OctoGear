<?php

namespace App\Repositories;

use App\Enums\SupportActor;
use App\Models\SupportConversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SupportConversationRepository
{
    public function query(): Builder
    {
        return SupportConversation::query()->with(['user', 'latestMessage'])->withCount([
            'messages as user_unread_count' => fn ($q) => $q->where('sender_kind', SupportActor::Admin)->whereColumn('support_messages.id', '>', 'support_conversations.user_read_through_id'),
            'messages as support_unread_count' => fn ($q) => $q->where('sender_kind', SupportActor::User)->whereColumn('support_messages.id', '>', 'support_conversations.support_read_through_id'),
        ]);
    }

    public function forUser(int $id): ?SupportConversation
    {
        return $this->query()->where('user_id', $id)->first();
    }

    public function summary(int $id): SupportConversation
    {
        return $this->query()->findOrFail($id);
    }

    public function index(array $filters)
    {
        return $this->query()
            ->when(($filters['status'] ?? 'open') !== 'all', fn ($q) => $q->where('status', $filters['status'] ?? 'open'))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->whereHas('user', fn ($u) => $u->where('type', $type)))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($q) use ($term, $search) {
                    if (ctype_digit($search)) {
                        $q->where('id', $search)->orWhereHas('user', fn ($u) => $u->where('full_name', 'like', $term)->orWhere('mobile', 'like', $term));
                    } else {
                        $q->whereHas('user', fn ($u) => $u->where('full_name', 'like', $term)->orWhere('mobile', 'like', $term));
                    }
                });
            })
            ->when($filters['unread'] ?? false, fn ($q) => $q->whereHas('messages', fn ($m) => $m->where('sender_kind', SupportActor::User)->whereColumn('support_messages.id', '>', 'support_conversations.support_read_through_id')))
            ->orderByDesc('last_activity_at')->orderByDesc('id')->paginate($filters['per_page'] ?? 20);
    }

    public function events(int $id, int $perPage)
    {
        return DB::table('support_conversation_events')
            ->where('support_conversation_id', $id)->orderByDesc('id')->paginate($perPage);
    }

    public function timeline(SupportConversation $conversation, array $filters): array
    {
        $forward = isset($filters['after_id']);
        $query = $conversation->messages()
            ->when(isset($filters['before_id']), fn ($q) => $q->where('id', '<', $filters['before_id']))
            ->when($forward, fn ($q) => $q->where('id', '>', $filters['after_id']))
            ->orderBy('id', $forward ? 'asc' : 'desc');
        $rows = $query->limit(41)->get();

        return ['messages' => $rows->take(40)->sortByDesc('id')->values(), 'has_more' => $rows->count() > 40];
    }
}
