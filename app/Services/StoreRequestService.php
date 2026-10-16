<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Owns the provider store-request onboarding workflow.
 *
 * The store request is created only after the provider's mobile has been
 * verified via a one-time token produced by the shared OtpService (intent
 * 'store'). It reuses OtpService rather than duplicating OTP logic.
 */
class StoreRequestService
{
    private const TOKEN_INTENT = 'store';

    public function __construct(private OtpService $otp, private ImageStorageService $images) {}

    /**
     * Send an OTP to the provider's mobile to start the store-request flow.
     */
    public function sendMobileOtp(string $mobile, User $user): ?string
    {
        $this->ensureMobileDiffersFromAccount($user, $mobile);

        return $this->otp->sendOtp($mobile);
    }

    /**
     * Verify the OTP and return a one-time token for submitting the request.
     *
     * @throws BusinessRuleException when the OTP is invalid
     */
    public function verifyMobileOtp(string $mobile, string $otp): string
    {
        if (! $this->otp->verifyOtp($mobile, $otp)) {
            throw new BusinessRuleException(
                'Invalid verification code.',
                'auth.otp.rate_limited',
                ['max' => 5],
                422,
            );
        }

        return $this->otp->createPendingToken(self::TOKEN_INTENT, $mobile);
    }

    /**
     * Apply to become a provider; the user stays a customer until admin approval.
     *
     * @throws BusinessRuleException when the token is missing, already used,
     *                               or the store mobile matches the account mobile
     */
    public function becomeProvider(User $user, array $data): StoreRequest
    {
        $token = $data['temp_token'] ?? null;

        $verifiedMobile = $token
            ? $this->otp->consumePendingToken(self::TOKEN_INTENT, $token)
            : null;

        if (! $verifiedMobile) {
            throw new BusinessRuleException(
                'The verification token is invalid or has expired.',
                'auth.register.token_invalid',
                [],
                422,
            );
        }

        $this->ensureMobileDiffersFromAccount($user, $verifiedMobile);

        return $this->createRequest($user, $data, $verifiedMobile, firstApplication: true);
    }

    /**
     * Submit a store request directly without OTP verification. Meant for an
     * already-onboarded provider adding another store.
     *
     * @throws BusinessRuleException when the store mobile matches the account mobile
     */
    public function createForProvider(User $provider, array $data): StoreRequest
    {
        $this->ensureMobileDiffersFromAccount($provider, $data['mobile']);

        return $this->createRequest($provider, $data, $data['mobile']);
    }

    private function createRequest(User $user, array $data, string $mobile, bool $firstApplication = false): StoreRequest
    {
        $storedFiles = [];
        $key = $data['idempotency_key'] ?? null;
        $companies = array_map('intval', $data['company_ids'] ?? []);
        sort($companies);
        $fingerprint = $key === null ? null : hash('sha256', json_encode([
            $mobile, $data['name'], $data['nick_name'], $data['employee_name'],
            $data['url_location'], $data['commercial_registration_number'],
            (int) $data['city_id'], $companies,
            hash_file('sha256', $data['commercial_registration_picture']->getRealPath()),
        ], JSON_THROW_ON_ERROR));

        try {
            $storeRequest = DB::transaction(function () use ($user, $data, $mobile, $firstApplication, $key, $fingerprint, &$storedFiles) {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

                if ($key !== null) {
                    $existing = StoreRequest::withTrashed()->where('user_id', $user->id)
                        ->where('idempotency_key', $key)->first();
                    if ($existing) {
                        if ($existing->trashed() || ! hash_equals($existing->idempotency_fingerprint ?? '', $fingerprint)) {
                            throw new BusinessRuleException(messageKey: 'auth.validation.idempotency_key.conflict', statusCode: 409);
                        }

                        return $existing;
                    }
                }
                if (! $firstApplication && (
                    Store::query()->where('mobile', $mobile)->exists() ||
                    $lockedUser->storeRequests()->where('mobile', $mobile)->where('request_status', RequestStatus::Pending)->exists()
                )) {
                    throw ValidationException::withMessages(['mobile' => [__('auth.store.validation.mobile.unique')]]);
                }

                if ($firstApplication) {
                    if (! $lockedUser->isCustomer() || $lockedUser->storeRequests()->exists()) {
                        throw new BusinessRuleException('Account already onboarded.', 'auth.general.forbidden', [], 409);
                    }
                }

                $image = $this->images->store(
                    $data['commercial_registration_picture'],
                    "store-requests/{$user->id}/registration",
                    $storedFiles,
                );

                return $lockedUser->storeRequests()->create([
                    ...Arr::except($data, ['temp_token', 'mobile', 'commercial_registration_picture']),
                    ...StoreMediaService::registrationAttributes($image),
                    'mobile' => $mobile,
                    'request_status' => RequestStatus::Pending,
                    'idempotency_key' => $key,
                    'idempotency_fingerprint' => $fingerprint,
                ]);
            });

            return $storeRequest;
        } catch (Throwable $exception) {
            $this->images->cleanup($storedFiles);

            throw $exception;
        }
    }

    public function resubmit(StoreRequest $request, array $data): StoreRequest
    {
        if ($request->store_id !== null) {
            throw new BusinessRuleException(messageKey: 'store_changes.new_request', statusCode: 409);
        }
        $storedFiles = [];
        $oldImage = null;
        try {
            $result = DB::transaction(function () use ($request, $data, &$storedFiles, &$oldImage) {
                $locked = StoreRequest::query()->lockForUpdate()->findOrFail($request->id);
                if ($locked->request_status !== RequestStatus::Rejected) {
                    throw new BusinessRuleException('Only rejected requests can be corrected.', 'auth.admin.store_requests.already_processed', [], 409);
                }
                $attributes = Arr::except($data, ['commercial_registration_picture', 'temp_token', 'mobile']);
                if (isset($data['commercial_registration_picture'])) {
                    $oldImage = $locked->registrationImage();
                    $image = $this->images->store($data['commercial_registration_picture'], "store-requests/{$locked->user_id}/registration", $storedFiles);
                    $attributes = [...$attributes, ...StoreMediaService::registrationAttributes($image)];
                }
                if (! $locked->hasRegistrationImage() && ! isset($data['commercial_registration_picture'])) {
                    throw ValidationException::withMessages([
                        'commercial_registration_picture' => [__('validation.required', ['attribute' => 'commercial_registration_picture'])],
                    ]);
                }
                $locked->update([...$attributes, 'request_status' => RequestStatus::Pending, 'rejection_reason' => null, 'processed_by' => null, 'processed_at' => null]);
                if ($oldImage) {
                    $this->images->deleteAfterCommit([$oldImage]);
                }

                return $locked->load(['city', 'pictures']);
            });
        } catch (Throwable $exception) {
            $this->images->cleanup($storedFiles);
            throw $exception;
        }

        return $result;
    }

    private function ensureMobileDiffersFromAccount(User $user, string $mobile): void
    {
        if ($mobile === $user->mobile) {
            throw new BusinessRuleException(
                'The store mobile cannot be the same as your account mobile.',
                'auth.store.validation.mobile.same_as_account',
                [],
                422,
            );
        }
    }
}
