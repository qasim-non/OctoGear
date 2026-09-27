<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\UserType;
use App\Exceptions\BusinessRuleException;
use App\Models\StoreRequest;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
    public function sendMobileOtp(string $mobile, User $user): void
    {
        $this->ensureMobileDiffersFromAccount($user, $mobile);

        $this->otp->sendOtp($mobile);
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
     * Become a provider: submit a store request after verifying the store mobile,
     * promote the user to ServiceProvider, and return the request.
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

        return $this->createRequest($user, $data, $verifiedMobile, promote: true);
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

    private function createRequest(User $user, array $data, string $mobile, bool $promote = false): StoreRequest
    {
        $storedFiles = [];

        try {
            $storeRequest = DB::transaction(function () use ($user, $data, $mobile, $promote, &$storedFiles) {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

                if ($promote) {
                    $lockedUser->update(['type' => UserType::ServiceProvider]);
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
                ]);
            });

            if ($promote) {
                $user->setAttribute('type', UserType::ServiceProvider);
            }

            return $storeRequest;
        } catch (Throwable $exception) {
            $this->images->cleanup($storedFiles);

            throw $exception;
        }
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
