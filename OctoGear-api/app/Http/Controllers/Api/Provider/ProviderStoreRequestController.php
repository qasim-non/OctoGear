<?php

namespace App\Http\Controllers\Api\Provider;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Provider\StoreStoreRequestRequest;
use App\Http\Resources\StoreRequestResource;
use App\Models\StoreRequest;
use App\Services\OtpService;

class ProviderStoreRequestController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    public function sendMobileOtp(SendOtpRequest $request)
    {
        $this->otpService->sendOtp($request->validated('mobile'));

        return $this->success(null, __('auth.otp.sent'));
    }

    public function verifyMobileOtp(VerifyOtpRequest $request)
    {
        $data = $request->validated();

        if (!$this->otpService->verifyOtp($data['mobile'], $data['otp'])) {
            return $this->error(__('auth.otp.rate_limited', ['max' => 5]), 422);
        }

        $tempToken = $this->otpService->createPendingToken('store', $data['mobile']);

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

        $mobile = $this->otpService->consumePendingToken('store', $request->validated('temp_token'));

        if (!$mobile) {
            return $this->error(__('auth.register.token_invalid'), 422);
        }

        $storeRequest = auth()->user()
            ->storeRequests()
            ->create(array_merge($request->safe()->except('temp_token'), [
                'mobile'         => $mobile,
                'request_status' => RequestStatus::Pending,
            ]));

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
