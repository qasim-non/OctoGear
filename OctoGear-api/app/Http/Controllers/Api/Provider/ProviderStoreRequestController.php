<?php

namespace App\Http\Controllers\Api\Provider;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Provider\StoreStoreRequestDirectRequest;
use App\Http\Requests\Provider\StoreStoreRequestRequest;
use App\Http\Resources\StoreRequestResource;
use App\Models\StoreRequest;
use App\Services\StoreRequestService;

class ProviderStoreRequestController extends Controller
{
    public function __construct(private StoreRequestService $storeRequests) {}

    public function sendMobileOtp(SendOtpRequest $request)
    {
        $user = auth()->user();

        $this->storeRequests->sendMobileOtp($request->validated('mobile'), $user);

        return $this->success(null, __('auth.otp.sent'));
    }

    public function verifyMobileOtp(VerifyOtpRequest $request)
    {
        $data = $request->validated();

        $tempToken = $this->storeRequests->verifyMobileOtp($data['mobile'], $data['otp']);

        return $this->success(['temp_token' => $tempToken], __('auth.otp.verified'));
    }

    public function index()
    {
        $this->authorize('viewAny', StoreRequest::class);

        $requests = auth()->user()
            ->storeRequests()
            ->with('city')
            ->latest()
            ->get();

        return $this->success(StoreRequestResource::collection($requests));
    }

    public function store(StoreStoreRequestRequest $request)
    {
        $this->authorize('create', StoreRequest::class);

        $storeRequest = $this->storeRequests->becomeProvider($request->user(), $request->validated());

        $storeRequest->load('city');

        return $this->created([
            'store_request' => new StoreRequestResource($storeRequest),
            'type' => UserType::ServiceProvider->value,
        ], __('auth.store.become_provider'));
    }

    public function storeDirect(StoreStoreRequestDirectRequest $request)
    {
        $this->authorize('create', StoreRequest::class);

        $storeRequest = $this->storeRequests->createForProvider($request->user(), $request->validated());

        $storeRequest->load('city');

        return $this->created(new StoreRequestResource($storeRequest));
    }

    public function show(StoreRequest $storeRequest)
    {
        $this->authorize('view', $storeRequest);

        $storeRequest->load('city');

        return $this->success(new StoreRequestResource($storeRequest));
    }
}
