<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\AuthorizeChatChannelRequest;
use App\Models\Conversation;
use App\Services\ChatRealtimeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class ChatRealtimeController extends Controller
{
    public function show(Request $request, ChatRealtimeService $realtime)
    {
        $this->authorize('subscribe', Conversation::class);

        return $this->success($realtime->configuration($request->user()))
            ->header('Cache-Control', 'no-store, private');
    }

    public function authorizeChannel(AuthorizeChatChannelRequest $request)
    {
        abort_unless(config('chat_realtime.enabled'), 503);
        // The Form Request policy has authorized this exact session channel.
        $authorization = Broadcast::connection('reverb')->validAuthenticationResponse($request, true);

        return $this->success(is_string($authorization) ? json_decode($authorization, true, flags: JSON_THROW_ON_ERROR) : $authorization)
            ->header('Cache-Control', 'no-store, private');
    }
}
