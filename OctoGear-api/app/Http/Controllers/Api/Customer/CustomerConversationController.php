<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreConversationRequest;
use App\Http\Requests\Customer\StoreMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;

class CustomerConversationController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Conversation::class);

        $user = auth()->user();

        $conversations = Conversation::query()
            ->where('customer_id', $user->id)
            ->with(['provider', 'customer'])
            ->withLatestMessage()
            ->withUnreadCount($user)
            ->latest()
            ->paginate(15);

        return $this->paginated($conversations->through(fn ($conversation) => new ConversationResource($conversation)));
    }

    public function store(StoreConversationRequest $request)
    {
        $this->authorize('create', Conversation::class);

        $user = auth()->user();

        $conversation = Conversation::firstOrCreate([
            'customer_id' => $user->id,
            'provider_id' => $request->provider_id,
        ]);

        $conversation->load(['provider', 'customer']);

        return $this->success(new ConversationResource($conversation));
    }

    public function messages(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $messages = $conversation->messages()
            ->with('sender')
            ->latest()
            ->paginate(20);

        return $this->paginated($messages->through(fn ($message) => new MessageResource($message)));
    }

    public function sendMessage(StoreMessageRequest $request, Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $message = $conversation->messages()->create([
            'content'    => $request->content,
            'sender_id'  => auth()->id(),
            'is_read'    => false,
        ]);

        $message->load('sender');

        return $this->created(new MessageResource($message));
    }
}
