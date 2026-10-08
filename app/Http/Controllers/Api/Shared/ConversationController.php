<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StartOfferConversationRequest;
use App\Http\Requests\Shared\ConversationIndexRequest;
use App\Http\Requests\Shared\ConversationTimelineRequest;
use App\Http\Requests\Shared\MarkConversationReadRequest;
use App\Http\Requests\Shared\PaginationRequest;
use App\Http\Requests\Shared\StoreConversationRequest;
use App\Http\Requests\Shared\StoreMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\FirstConversationMessageResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\MessageTimelineResource;
use App\Http\Resources\OfferConversationResource;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        return $this->success(new ConversationResource($this->chat->details($conversation, $request->user())));
    }

    public function offerConversation(Request $request, Order $order, OrderOffer $offer): JsonResponse
    {
        $this->authorize('viewConversation', [$offer, $order]);

        return $this->success(new OfferConversationResource($this->chat->offerConversation($offer, $request->user())));
    }

    public function firstMessage(StartOfferConversationRequest $request, Order $order, OrderOffer $offer): JsonResponse
    {
        $message = $this->chat->start($offer, $request->user(), $request->validated());

        return $this->success(new FirstConversationMessageResource(
            $message,
            $this->chat->details($message->conversation, $request->user()),
        ));
    }

    public function timeline(ConversationTimelineRequest $request, Conversation $conversation): JsonResponse
    {
        $input = $request->validated();

        return $this->success(new MessageTimelineResource($this->chat->timeline(
            $conversation,
            isset($input['before_id']) ? (int) $input['before_id'] : null,
            isset($input['after_id']) ? (int) $input['after_id'] : null,
        )));
    }

    public function read(MarkConversationReadRequest $request, Conversation $conversation): JsonResponse
    {
        $throughId = $request->integer('through_id');
        $this->chat->markRead($conversation, $request->user(), $throughId);

        return $this->success(['through_id' => $throughId]);
    }

    public function index(ConversationIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);
        $conversations = $this->chat->inbox($request->user(), $request->boolean('with_messages'), $request->integer('page', 1));

        return $this->paginated($conversations->through(fn ($conversation) => new ConversationResource($conversation)));
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        return $this->success(new ConversationResource($this->chat->createDirect($request->user(), $request->validated())));
    }

    public function messages(PaginationRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $messages = $this->chat->messages($conversation, $request->integer('page', 1));

        return $this->paginated($messages->through(fn ($message) => new MessageResource($message)));
    }

    public function sendMessage(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $message = $this->chat->send($conversation, $request->user(), $request->validated());

        return $this->created(new MessageResource($message));
    }
}
