<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\StoreStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Owns the admin review workflow for provider store requests.
 *
 * - index:  paginated list, optionally filtered by status
 * - accept: turns a pending request into a live Store (transactional)
 * - reject: denies a pending request with a reason
 *
 * State transitions are guarded here (pending is the only status that can be
 * processed), matching the "middleware gates roles/routes, services guard state"
 * convention.
 */
class AdminStoreRequestService
{
    public function __construct(private ImageStorageService $images) {}

    public function index(?string $status): LengthAwarePaginator
    {
        return StoreRequest::query()
            ->with(['user', 'city'])
            ->when($status, fn ($query) => $query->where('request_status', $status))
            ->latest()
            ->paginate(15);
    }

    /**
     * @throws BusinessRuleException when the request is not pending or the
     *                               store mobile is already taken
     */
    public function accept(StoreRequest $storeRequest, Admin $admin): Store
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($storeRequest, $admin, &$storedFiles) {
                $storeRequest = StoreRequest::query()->lockForUpdate()->findOrFail($storeRequest->getKey());
                $this->ensurePending($storeRequest);
                $this->ensureStoreMobileFree($storeRequest->mobile);

                $registration = $storeRequest->registrationImage();

                $store = $storeRequest->user->stores()->create([
                    'name' => $storeRequest->name,
                    'mobile' => $storeRequest->mobile,
                    'nick_name' => $storeRequest->nick_name,
                    'employee_name' => $storeRequest->employee_name,
                    'url_location' => $storeRequest->url_location,
                    'commercial_registration_number' => $storeRequest->commercial_registration_number,
                    ...StoreMediaService::registrationAttributes($registration),
                    'city_id' => $storeRequest->city_id,
                    'status' => StoreStatus::Active,
                ]);

                // Legacy references remain untrusted. Real uploads are copied so
                // deleting the request cannot remove the accepted store's document.
                if ($storeRequest->hasRegistrationImage()) {
                    $registration = $this->images->copy($registration, "stores/{$store->id}/registration", $storedFiles);
                    $store->update(StoreMediaService::registrationAttributes($registration));
                }

                $storeRequest->update([
                    'request_status' => RequestStatus::Accepted,
                    'rejection_reason' => null,
                    'processed_by' => $admin->employee_id,
                ]);

                return $store;
            });
        } catch (Throwable $exception) {
            $this->images->cleanup($storedFiles);

            throw $exception;
        }
    }

    public function reject(StoreRequest $storeRequest, Admin $admin, string $reason): StoreRequest
    {
        $this->ensurePending($storeRequest);

        $storeRequest->update([
            'request_status' => RequestStatus::Rejected,
            'rejection_reason' => $reason,
            'processed_by' => $admin->employee_id,
        ]);

        return $storeRequest->refresh();
    }

    private function ensurePending(StoreRequest $storeRequest): void
    {
        if ($storeRequest->request_status !== RequestStatus::Pending) {
            throw new BusinessRuleException(
                'Store request has already been processed.',
                'auth.admin.store_requests.already_processed',
                [],
                422,
            );
        }
    }

    private function ensureStoreMobileFree(string $mobile): void
    {
        if (Store::where('mobile', $mobile)->exists()) {
            throw new BusinessRuleException(
                'A store with this mobile already exists.',
                'auth.admin.store_requests.store_mobile_taken',
                [],
                422,
            );
        }
    }
}
