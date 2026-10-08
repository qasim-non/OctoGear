<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\RegisterDeviceTokenRequest;
use App\Models\DeviceToken;
use App\Services\DeviceTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeviceTokenController extends Controller
{
    public function __construct(private DeviceTokenService $devices) {}

    public function store(RegisterDeviceTokenRequest $request): JsonResponse
    {
        $this->devices->register($request->user(), $request->validated());

        return $this->success(null);
    }

    public function destroy(Request $request): JsonResponse
    {
        Gate::authorize('manage', DeviceToken::class);
        $this->devices->unregister($request->user());

        return $this->success(null);
    }
}
