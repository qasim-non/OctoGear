<?php

namespace App\Http\Controllers\Api\Shared;

use App\Enums\SupportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ReadSupportRequest;
use App\Http\Requests\Support\SendSupportMessageRequest;
use App\Http\Requests\Support\SupportRequest;
use App\Http\Requests\Support\SupportStatusRequest;
use App\Http\Requests\Support\SupportTimelineRequest;
use App\Http\Resources\SupportConversationResource;
use App\Http\Resources\SupportMessageResource;
use App\Models\SupportConversation;
use App\Repositories\SupportConversationRepository;
use App\Services\SupportChatService;

class SupportConversationController extends Controller
{
    public function __construct(private SupportConversationRepository $conversations, private SupportChatService $service) {}

    public function show(SupportRequest $request)
    {
        $conversation = $this->conversations->forUser($request->user()->id);

        return $this->success($conversation ? new SupportConversationResource($conversation) : null)->header('Cache-Control', 'no-store, private');
    }

    public function send(SendSupportMessageRequest $request)
    {
        $result = $this->service->send($request->user(), $request->validated());

        return $this->success(['conversation' => new SupportConversationResource($result['conversation']), 'message' => new SupportMessageResource($result['message'])]);
    }

    public function timeline(SupportTimelineRequest $request)
    {
        $conversation = $this->current($request);
        $page = $this->conversations->timeline($conversation, $request->validated());

        return $this->success(['messages' => SupportMessageResource::collection($page['messages']), 'has_more' => $page['has_more'], 'conversation' => new SupportConversationResource($conversation)]);
    }

    public function read(ReadSupportRequest $request)
    {
        return $this->success(new SupportConversationResource($this->service->read($request->user(), $this->current($request), $request->integer('through_id'))));
    }

    public function reopen(SupportStatusRequest $request)
    {
        return $this->success(new SupportConversationResource($this->service->status($request->user(), $this->current($request), SupportStatus::Open, $request->integer('version'))));
    }

    private function current(SupportRequest $request): SupportConversation
    {
        $conversation = $this->conversations->forUser($request->user()->id);
        abort_unless($conversation, 404);

        return $conversation;
    }
}
