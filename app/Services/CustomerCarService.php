<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\CustomerCar;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Owns customer-car metadata persistence. Photos are created through the
 * dedicated private-media workflow rather than being accepted as client paths.
 */
class CustomerCarService
{
    public function __construct(private CustomerCarPhotoService $photos) {}

    /** The enclosing order transaction and submission key own atomicity and retries. */
    public function saveRequestVehicle(User $customer, array $vehicle): CustomerCar
    {
        return $customer->customerCars()->create(Arr::only($vehicle, [
            'car_name_id', 'manufacturing_year', 'transmission_type', 'color_id', 'fuel_type',
        ]));
    }

    public function create(User $customer, array $data): CustomerCar
    {
        $idempotencyKey = $data['idempotency_key'];
        $fingerprint = $this->submissionFingerprint($data);

        $storedFiles = [];

        try {
            return DB::transaction(function () use ($customer, $data, $idempotencyKey, $fingerprint, &$storedFiles) {
                $existing = $this->findByIdempotencyKey(
                    customer: $customer,
                    idempotencyKey: $idempotencyKey,
                    lockForUpdate: true,
                );

                if ($existing && ($replayedCar = $this->resolveExistingSubmission($existing, $fingerprint))) {
                    return $replayedCar;
                }

                $car = $customer->customerCars()->create(
                    [
                        ...Arr::except($data, ['pictures']),
                        'idempotency_fingerprint' => $fingerprint,
                    ]
                );

                $this->photos->storeInitial(
                    $car,
                    $data['pictures'] ?? [],
                    $storedFiles,
                );

                return $car;
            });
        } catch (QueryException $exception) {
            // A rolled-back attempt can have written images before a unique
            // constraint failure. Clean those files even when replay succeeds.
            $this->photos->cleanupStoredFiles($storedFiles);

            // A concurrent retry may not see the first transaction until its
            // unique customer/key insert commits. A matching active request
            // may replay that car; a changed request remains a safe conflict.
            if ($existing = $this->findByIdempotencyKey($customer, $idempotencyKey)) {
                if (! $existing->trashed() && ! $this->hasExpiredIdempotencyKey($existing)) {
                    if ($this->hasMatchingFingerprint($existing, $fingerprint)) {
                        return $existing;
                    }

                    $this->throwIdempotencyConflict();
                }
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->photos->cleanupStoredFiles($storedFiles);

            throw $exception;
        }
    }

    public function update(CustomerCar $car, array $data): CustomerCar
    {
        DB::transaction(function () use ($car, $data) {
            $car->update($data);
        });

        return $car;
    }

    /**
     * Clear completed idempotency records after their bounded replay window.
     * Soft-deleted cars are also cleared because they must never be replayed.
     */
    public function purgeExpiredIdempotencyKeys(): int
    {
        return CustomerCar::query()
            ->withTrashed()
            ->whereNotNull('idempotency_key')
            ->where(function ($query): void {
                $query
                    ->where('created_at', '<=', $this->idempotencyExpiryCutoff())
                    ->orWhereNotNull('deleted_at');
            })
            ->update([
                'idempotency_key' => null,
                'idempotency_fingerprint' => null,
            ]);
    }

    private function findByIdempotencyKey(
        User $customer,
        string $idempotencyKey,
        bool $lockForUpdate = false,
    ): ?CustomerCar {
        $query = CustomerCar::query()
            ->withTrashed()
            ->where('customer_id', $customer->getKey())
            ->where('idempotency_key', $idempotencyKey);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function resolveExistingSubmission(CustomerCar $existing, string $fingerprint): ?CustomerCar
    {
        // Never replay a deleted car. Releasing its key inside this
        // transaction permits a new valid submission to use that UUID.
        if ($existing->trashed() || $this->hasExpiredIdempotencyKey($existing)) {
            $this->releaseIdempotencyKey($existing);

            return null;
        }

        if (! $this->hasMatchingFingerprint($existing, $fingerprint)) {
            $this->throwIdempotencyConflict();
        }

        return $existing;
    }

    private function releaseIdempotencyKey(CustomerCar $car): void
    {
        $car->forceFill([
            'idempotency_key' => null,
            'idempotency_fingerprint' => null,
        ])->save();
    }

    private function hasExpiredIdempotencyKey(CustomerCar $car): bool
    {
        return $car->created_at === null
            || $car->created_at->lessThanOrEqualTo($this->idempotencyExpiryCutoff());
    }

    private function idempotencyExpiryCutoff(): Carbon
    {
        $hours = max(1, (int) config('customer_car_media.idempotency_retention_hours'));

        return now()->subHours($hours);
    }

    private function hasMatchingFingerprint(CustomerCar $car, string $fingerprint): bool
    {
        return is_string($car->idempotency_fingerprint)
            && hash_equals($car->idempotency_fingerprint, $fingerprint);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function submissionFingerprint(array $data): string
    {
        $pictures = [];

        foreach (array_values($data['pictures'] ?? []) as $sortOrder => $file) {
            if (! $file instanceof UploadedFile || ! is_string($path = $file->getRealPath())) {
                throw new RuntimeException('Unable to fingerprint customer-car media safely.');
            }

            $contentHash = hash_file('sha256', $path);

            if ($contentHash === false) {
                throw new RuntimeException('Unable to fingerprint customer-car media safely.');
            }

            $pictures[] = [
                'sort_order' => $sortOrder,
                'content_sha256' => $contentHash,
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
            ];
        }

        return hash('sha256', json_encode([
            'car_name_id' => (int) $data['car_name_id'],
            'manufacturing_year' => (int) $data['manufacturing_year'],
            'vehicle_plat_number' => (string) $data['vehicle_plat_number'],
            'color_id' => (int) $data['color_id'],
            'fuel_type' => (int) $data['fuel_type'],
            'pictures' => $pictures,
            // Keep old omitted/null requests byte-compatible with their saved
            // fingerprints, while detecting changed transmission selections.
            ...isset($data['transmission_type'])
                ? ['transmission_type' => $data['transmission_type']]
                : [],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function throwIdempotencyConflict(): never
    {
        throw new BusinessRuleException(
            message: __('auth.validation.idempotency_key.conflict'),
            messageKey: 'auth.validation.idempotency_key.conflict',
            statusCode: 409,
        );
    }
}
