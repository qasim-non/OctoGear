<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\StoreStatus;
use App\Enums\UserType;
use App\Events\StoreRequestReviewed;
use App\Exceptions\BusinessRuleException;
use App\Models\Admin;
use App\Models\CarCompany;
use App\Models\City;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Owns the admin review workflow for provider store requests.
 *
 * - index:  paginated list, optionally filtered by status
 * - accept: creates a store or applies an approved change (transactional)
 * - reject: denies a pending request with a reason
 *
 * State transitions are guarded here (pending is the only status that can be
 * processed), matching the "middleware gates roles/routes, services guard state"
 * convention.
 */
class AdminStoreRequestService
{
    public function __construct(private ImageStorageService $images, private StoreChangeRequestService $changes) {}

    public function index(?string $status, array $filters = []): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;

        return StoreRequest::query()
            ->with($this->reviewRelations())
            ->when($status, fn ($query) => $query->where('request_status', $status))
            ->when($filters['city_id'] ?? null, fn ($query, $cityId) => $query->where('city_id', $cityId))
            ->when(($filters['request_type'] ?? null) === 'new_store', fn ($query) => $query->whereNull('store_id'))
            ->when(($filters['request_type'] ?? null) === 'store_change', fn ($query) => $query->whereNotNull('store_id'))
            ->when($search !== null && $search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->where('name', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($owner) => $owner->where('full_name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%"))
            ))
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function show(StoreRequest $request): StoreRequest
    {
        $request->load($this->reviewRelations());
        $companyIds = array_unique([...($request->company_ids ?? []), ...($request->previous_details['company_ids'] ?? [])]);
        // Prepare labels once for detail review; resources must not query per field.
        $request->setRelation('reviewCompanies', CarCompany::withTrashed()->whereIn('id', $companyIds)->get(['id', 'name_en', 'name_ar']));
        $request->setRelation('previousCity', City::withTrashed()->find($request->previous_details['city_id'] ?? null));

        return $request;
    }

    private function reviewRelations(): array
    {
        return ['user', 'city', 'pictures'];
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
                $owner = User::query()->lockForUpdate()->findOrFail($storeRequest->user_id);
                $store = $storeRequest->store_id === null ? null : Store::query()->lockForUpdate()->findOrFail($storeRequest->store_id);
                $storeRequest = StoreRequest::query()->lockForUpdate()->findOrFail($storeRequest->getKey());
                $this->ensurePending($storeRequest);
                if ($store !== null) {
                    $result = $this->changes->apply($storeRequest, $store, $storedFiles);
                    $this->recordReview($storeRequest, $admin, RequestStatus::Accepted);

                    return $result;
                }
                $this->ensureStoreMobileFree($storeRequest->mobile);

                // Application submission keeps customer access. Approval is the
                // atomic hand-off to the provider role, alongside store creation.
                if ($owner->isCustomer()) {
                    $owner->update(['type' => UserType::ServiceProvider]);
                }

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

                $companyIds = CarCompany::query()->whereIn('id', $storeRequest->company_ids ?? [])->pluck('id');
                $store->companies()->sync($companyIds);

                // Legacy references remain untrusted. Real uploads are copied so
                // deleting the request cannot remove the accepted store's document.
                if ($storeRequest->hasRegistrationImage()) {
                    $registration = $this->images->copy($registration, "stores/{$store->id}/registration", $storedFiles);
                    $store->update(StoreMediaService::registrationAttributes($registration));
                }

                $this->recordReview($storeRequest, $admin, RequestStatus::Accepted);

                return $store;
            });
        } catch (Throwable $exception) {
            $this->images->cleanup($storedFiles);

            throw $exception;
        }
    }

    public function reject(StoreRequest $storeRequest, Admin $admin, string $reason): StoreRequest
    {
        return DB::transaction(function () use ($storeRequest, $admin, $reason) {
            $locked = StoreRequest::query()->lockForUpdate()->findOrFail($storeRequest->id);
            $this->ensurePending($locked);
            $this->recordReview($locked, $admin, RequestStatus::Rejected, $reason);

            return $locked;
        });
    }

    /** Persist the review and its inbox receipt in the same transaction. */
    private function recordReview(StoreRequest $request, Admin $admin, RequestStatus $status, ?string $reason = null): void
    {
        $request->update([
            'request_status' => $status, 'rejection_reason' => $reason,
            'pending_store_id' => null, 'processed_by' => $admin->employee_id,
            'processed_at' => now(),
        ]);
        StoreRequestReviewed::dispatch($request);
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
