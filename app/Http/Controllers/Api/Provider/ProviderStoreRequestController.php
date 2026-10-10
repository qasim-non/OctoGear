<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Provider\ResubmitStoreRequestRequest;
use App\Http\Requests\Provider\StoreStoreRequestDirectRequest;
use App\Http\Requests\Provider\StoreStoreRequestRequest;
use App\Http\Resources\StoreRequestResource;
use App\Models\StoreRequest;
use App\Services\StoreRequestService;
use Illuminate\Http\Request;

class ProviderStoreRequestController extends Controller
{
    public function __construct(private StoreRequestService $storeRequests) {}

    public function sendMobileOtp(SendOtpRequest $request)
    {
        $user = auth()->user();

        $testOtp = $this->storeRequests->sendMobileOtp($request->validated('mobile'), $user);

        return $this->success($testOtp === null ? null : ['test_otp' => $testOtp], __('auth.otp.sent'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function verifyMobileOtp(VerifyOtpRequest $request)
    {
        $data = $request->validated();

        $tempToken = $this->storeRequests->verifyMobileOtp($data['mobile'], $data['otp']);

        return $this->success(['temp_token' => $tempToken], __('auth.otp.verified'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function index()
    {
        $requests = auth()->user()
            ->storeRequests()
            ->with('city')
            ->latest()
            ->get();

        return $this->success(StoreRequestResource::collection($requests));
    }

    public function store(StoreStoreRequestRequest $request)
    {
        $storeRequest = $this->storeRequests->becomeProvider($request->user(), $request->validated());

        $storeRequest->load('city');

        return $this->created([
            'store_request' => new StoreRequestResource($storeRequest),
            'type' => $request->user()->type->value,
        ], __('auth.store.application_submitted'));
    }

    public function storeDirect(StoreStoreRequestDirectRequest $request)
    {
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

    public function resubmit(ResubmitStoreRequestRequest $request, StoreRequest $storeRequest)
    {
        $this->authorize('view', $storeRequest);

        return $this->success(new StoreRequestResource(
            $this->storeRequests->resubmit($storeRequest, $request->validated()),
        ));
    }

    public function application(Request $request)
    {
        $application = $request->user()->storeRequests()->with('city')->latest('id')->first();

        return $this->success($application ? new StoreRequestResource($application) : null)
            ->header('Cache-Control', 'no-store, private');
    }
}
