<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\StartProviderOrderConversationRequest;
use App\Http\Resources\FirstConversationMessageResource;
use App\Http\Resources\ProviderOrderConversationResource;
use App\Models\Order;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderOrderConversationController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function show(Request $request, Order $order): JsonResponse
    {
        return $this->success(new ProviderOrderConversationResource(
            $this->chat->providerOrderConversation($order, $request->user()),
        ));
    }

    public function store(StartProviderOrderConversationRequest $request, Order $order): JsonResponse
    {
        $message = $this->chat->startProviderOrder($order, $request->user(), $request->validated());

        return $this->success(new FirstConversationMessageResource(
            $message, $this->chat->details($message->conversation, $request->user()),
        ));
    }
}
