<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SupportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\AuthorizeSupportChannelRequest;
use App\Http\Requests\Support\ReadSupportRequest;
use App\Http\Requests\Support\SendSupportMessageRequest;
use App\Http\Requests\Support\SupportIndexRequest;
use App\Http\Requests\Support\SupportRequest;
use App\Http\Requests\Support\SupportStatusRequest;
use App\Http\Requests\Support\SupportTimelineRequest;
use App\Http\Resources\SupportConversationResource;
use App\Http\Resources\SupportMessageResource;
use App\Models\SupportConversation;
use App\Repositories\SupportConversationRepository;
use App\Services\ChatRealtimeService;
use App\Services\SupportChatService;
use Illuminate\Support\Facades\Broadcast;

class AdminSupportConversationController extends Controller
{
    public function __construct(private SupportConversationRepository $conversations, private SupportChatService $service) {}

    public function index(SupportIndexRequest $request)
    {
        $page = $this->conversations->index($request->validated());
        $page->through(fn ($row) => new SupportConversationResource($row, true));

        return $this->paginated($page);
    }

    public function show(SupportRequest $request, SupportConversation $supportConversation)
    {
        return $this->success(new SupportConversationResource($this->conversations->summary($supportConversation->id), true));
    }

    public function send(SendSupportMessageRequest $request, SupportConversation $supportConversation)
    {
        $result = $this->service->send($request->user(), $request->validated(), $supportConversation);

        return $this->success(['conversation' => new SupportConversationResource($result['conversation'], true), 'message' => new SupportMessageResource($result['message'])]);
    }

    public function timeline(SupportTimelineRequest $request, SupportConversation $supportConversation)
    {
        $page = $this->conversations->timeline($supportConversation, $request->validated());

        return $this->success(['messages' => SupportMessageResource::collection($page['messages']), 'has_more' => $page['has_more'],
            'conversation' => new SupportConversationResource($this->conversations->summary($supportConversation->id), true)]);
    }

    public function read(ReadSupportRequest $request, SupportConversation $supportConversation)
    {
        return $this->success(new SupportConversationResource($this->service->read($request->user(), $supportConversation, $request->integer('through_id')), true));
    }

    public function status(SupportStatusRequest $request, SupportConversation $supportConversation)
    {
        return $this->success(new SupportConversationResource($this->service->status($request->user(), $supportConversation, SupportStatus::from($request->string('status')->toString()), $request->integer('version')), true));
    }

    public function events(SupportIndexRequest $request, SupportConversation $supportConversation)
    {
        return $this->paginated($this->conversations->events($supportConversation->id, $request->integer('per_page', 20)));
    }

    public function realtime(SupportIndexRequest $request, ChatRealtimeService $realtime)
    {
        return $this->success($realtime->configuration($request->user()))->header('Cache-Control', 'no-store, private');
    }

    public function authorizeChannel(AuthorizeSupportChannelRequest $request)
    {
        abort_unless(config('chat_realtime.enabled'), 503);
        $auth = Broadcast::connection('reverb')->validAuthenticationResponse($request, true);

        return $this->success(is_string($auth) ? json_decode($auth, true, flags: JSON_THROW_ON_ERROR) : $auth)->header('Cache-Control', 'no-store, private');
    }
}
