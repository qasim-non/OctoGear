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
        $this->ensurePending($storeRequest);
        $this->ensureStoreMobileFree($storeRequest->mobile);

        return DB::transaction(function () use ($storeRequest, $admin) {
            $store = $storeRequest->user->stores()->create([
                'name' => $storeRequest->name,
                'mobile' => $storeRequest->mobile,
                'nick_name' => $storeRequest->nick_name,
                'employee_name' => $storeRequest->employee_name,
                'url_location' => $storeRequest->url_location,
                'commercial_registration_number' => $storeRequest->commercial_registration_number,
                'commercial_registration_picture' => $storeRequest->commercial_registration_picture,
                'city_id' => $storeRequest->city_id,
                'status' => StoreStatus::Active,
            ]);

            $storeRequest->update([
                'request_status' => RequestStatus::Accepted,
                'rejection_reason' => null,
                'processed_by' => $admin->employee_id,
            ]);

            return $store;
        });
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
