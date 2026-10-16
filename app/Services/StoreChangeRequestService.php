<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CarCompany;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Approval proposals own their media; submission never mutates the live store. */
class StoreChangeRequestService
{
    private const FIELDS = ['name', 'nick_name', 'employee_name', 'url_location', 'commercial_registration_number', 'city_id'];

    public function __construct(private ImageStorageService $images) {}

    public static function snapshot(Store $store): array
    {
        $store->loadMissing(['companies', 'pictures']);

        return [
            ...$store->only(self::FIELDS),
            'mobile' => $store->mobile,
            'company_ids' => $store->companies->pluck('id')->sort()->values()->all(),
            // Opaque digests detect changed media without disclosing storage paths.
            'registration_fingerprint' => hash('sha256', json_encode($store->registrationImage(), JSON_THROW_ON_ERROR)),
            'pictures' => $store->pictures->map(fn ($picture) => [
                'id' => $picture->id,
                'fingerprint' => hash('sha256', json_encode($picture->image(), JSON_THROW_ON_ERROR)),
            ])->all(),
        ];
    }

    public static function version(Store $store): string
    {
        return hash('sha256', json_encode(self::snapshot($store), JSON_THROW_ON_ERROR));
    }

    public function submit(User $owner, Store $store, array $data): StoreRequest
    {
        $files = [];
        $fingerprintData = $data;
        foreach (['commercial_registration_picture', 'pictures'] as $field) {
            if (isset($data[$field])) {
                $fingerprintData[$field] = array_map(fn ($file) => hash_file('sha256', $file->getRealPath()), Arr::wrap($data[$field]));
            }
        }
        ksort($fingerprintData);
        $fingerprint = hash('sha256', json_encode([$store->id, $fingerprintData], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($owner, $store, $data, $fingerprint, &$files) {
                // Match onboarding/admin lock ordering: owner, store, request.
                User::query()->lockForUpdate()->findOrFail($owner->id);
                $locked = Store::query()->where('user_id', $owner->id)->lockForUpdate()->findOrFail($store->id);
                $existing = StoreRequest::withTrashed()->where('user_id', $owner->id)
                    ->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    if ($existing->trashed() || ! hash_equals($existing->idempotency_fingerprint ?? '', $fingerprint)) {
                        throw new BusinessRuleException(messageKey: 'auth.validation.idempotency_key.conflict', statusCode: 409);
                    }

                    return $existing->load($this->requestRelations());
                }
                if (StoreRequest::withTrashed()->where('pending_store_id', $locked->id)->exists()) {
                    throw new BusinessRuleException(messageKey: 'store_changes.pending', statusCode: 409);
                }
                if (! hash_equals(self::version($locked), $data['base_version'])) {
                    throw new BusinessRuleException(messageKey: 'store_changes.stale', statusCode: 409);
                }
                $before = self::snapshot($locked);
                $attributes = [...$locked->only(self::FIELDS), ...Arr::only($data, self::FIELDS)];
                $companies = array_map('intval', $data['company_ids'] ?? $before['company_ids']);
                sort($companies);
                // Validate current selections after receipt lookup so an identical
                // retry still resolves after approval replaces photos or a brand retires.
                if (CarCompany::query()->whereIn('id', $companies)->count() !== count($companies)) {
                    throw ValidationException::withMessages(['company_ids' => [__('store_changes.brands_invalid')]]);
                }
                $pictures = $locked->pictures->keyBy('id');
                $retained = array_map('intval', $data['retained_picture_ids'] ?? $pictures->keys()->all());
                $uploads = $data['pictures'] ?? [];
                if (array_diff($retained, $pictures->keys()->all()) || count($retained) + count($uploads) > config('images.max_files')) {
                    throw ValidationException::withMessages(['pictures' => [__('store_changes.gallery_invalid')]]);
                }
                if ($attributes == $locked->only(self::FIELDS) && $companies === $before['company_ids'] &&
                    $retained === $pictures->keys()->all() && $uploads === [] && ! isset($data['commercial_registration_picture'])) {
                    throw ValidationException::withMessages(['change_reason' => [__('store_changes.no_changes')]]);
                }
                $directory = "store-requests/{$owner->id}/{$data['idempotency_key']}";
                $registration = isset($data['commercial_registration_picture'])
                    ? $this->images->store($data['commercial_registration_picture'], "$directory/registration", $files)
                    : ($locked->hasRegistrationImage()
                        ? $this->images->copy($locked->registrationImage(), "$directory/registration", $files)
                        : $locked->registrationImage());
                // Legacy records may have an untrusted path without a disk.
                // Preserve that metadata; it is never streamed or copied.
                $proposal = $owner->storeRequests()->create([
                    ...$attributes,
                    ...StoreMediaService::registrationAttributes($registration),
                    'store_id' => $locked->id, 'pending_store_id' => $locked->id,
                    'mobile' => $locked->mobile, 'company_ids' => $companies,
                    'change_reason' => $data['change_reason'], 'previous_details' => $before,
                    'base_version' => $data['base_version'], 'request_status' => RequestStatus::Pending,
                    'idempotency_key' => $data['idempotency_key'], 'idempotency_fingerprint' => $fingerprint,
                ]);
                foreach ($retained as $position => $id) {
                    $image = $this->images->copy($pictures[$id]->image(), "$directory/pictures", $files);
                    $proposal->pictures()->create([...$image, 'sort_order' => $position]);
                }
                foreach ($uploads as $position => $upload) {
                    $image = $this->images->store($upload, "$directory/pictures", $files);
                    $proposal->pictures()->create([...$image, 'sort_order' => count($retained) + $position]);
                }

                return $proposal->load($this->requestRelations());
            });
        } catch (Throwable $exception) {
            $this->images->cleanup($files);
            throw $exception;
        }
    }

    /** Called inside the admin transaction with the store and request locked. */
    public function apply(StoreRequest $proposal, Store $store, array &$files): Store
    {
        if ($store->user_id !== $proposal->user_id || $store->mobile !== $proposal->mobile ||
            ! hash_equals($proposal->base_version ?? '', self::version($store))) {
            throw new BusinessRuleException(messageKey: 'store_changes.stale', statusCode: 409);
        }
        $companyIds = $proposal->company_ids ?? [];
        if (CarCompany::query()->whereIn('id', $companyIds)->count() !== count($companyIds)) {
            throw new BusinessRuleException(messageKey: 'store_changes.stale', statusCode: 409);
        }
        $oldFiles = [$store->registrationImage(), ...$store->pictures->map->image()->all()];
        $attributes = $proposal->only(self::FIELDS);
        if ($proposal->hasRegistrationImage()) {
            $registration = $this->images->copy($proposal->registrationImage(), "stores/{$store->id}/registration", $files);
            $attributes = [...$attributes, ...StoreMediaService::registrationAttributes($registration)];
        }
        $store->update($attributes); // Never accept mobile, ownership, or status from a proposal.
        $store->companies()->sync($companyIds);
        $store->pictures()->delete();
        foreach ($proposal->pictures as $picture) {
            $image = $this->images->copy($picture->image(), "stores/{$store->id}/pictures", $files);
            $store->pictures()->create([...$image, 'sort_order' => $picture->sort_order]);
        }
        $this->images->deleteAfterCommit($oldFiles);

        return $store;
    }

    private function requestRelations(): array
    {
        return ['city', 'pictures'];
    }
}
