<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\CustomerCar;
use App\Models\CustomerCarPicture;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Owns customer-car photo limits and records. File operations are shared with
 * every other image feature through ImageStorageService.
 */
class CustomerCarPhotoService
{
    public function __construct(private ImageStorageService $images) {}

    /**
     * Store initial photos while CustomerCarService owns the surrounding
     * database transaction. Newly written files are passed back for cleanup
     * if that outer transaction cannot commit.
     *
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    public function storeInitial(CustomerCar $car, array $files, array &$storedFiles): Collection
    {
        return $this->storeForCar($car, $files, $storedFiles);
    }

    /**
     * Add photos to an existing car. Locking the parent makes the total-photo
     * limit correct even if two upload requests arrive together.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function add(CustomerCar $car, array $files): Collection
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($car, $files, &$storedFiles) {
                $lockedCar = CustomerCar::query()
                    ->lockForUpdate()
                    ->findOrFail($car->getKey());

                return $this->storeForCar($lockedCar, $files, $storedFiles);
            });
        } catch (Throwable $exception) {
            $this->cleanupStoredFiles($storedFiles);

            throw $exception;
        }
    }

    public function stream(CustomerCarPicture $picture): ?StreamedResponse
    {
        return $this->images->stream($picture->getAttributes());
    }

    /**
     * Hide the photo and durably queue its file deletion in one transaction.
     * Failed cleanup retains hidden metadata and is retried by images:cleanup.
     */
    public function delete(CustomerCarPicture $picture): void
    {
        DB::transaction(function () use ($picture) {
            $picture->delete();
            $this->images->deleteAfterCommit([$picture->getAttributes()]);

            DB::afterCommit(function () use ($picture): void {
                try {
                    // The model guard verifies deletion before discarding
                    // metadata, including calls made outside this service.
                    $picture->forceDelete();
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        });
    }

    /**
     * Strictly remove every remaining private file before an Eloquent
     * CustomerCar force delete. Throwing here prevents the database cascade
     * from deleting metadata while a private file remains inaccessible.
     */
    public function purgeFilesForForceDelete(CustomerCar $car): void
    {
        $pictures = CustomerCarPicture::query()
            ->withTrashed()
            ->where('car_id', $car->getKey())
            ->whereNotNull('disk')
            ->whereNotNull('path')
            ->get();

        foreach ($pictures as $picture) {
            $this->images->delete($picture->getAttributes());
        }
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    public function cleanupStoredFiles(array $storedFiles): void
    {
        $this->images->cleanup($storedFiles);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    private function storeForCar(CustomerCar $car, array $files, array &$storedFiles): Collection
    {
        if ($files === []) {
            return new Collection;
        }

        $this->ensurePhotoLimit($car, count($files));

        $disk = config('customer_car_media.disk');
        $nextSortOrder = ($car->pictures()->max('sort_order') ?? -1) + 1;
        $pictures = [];

        foreach ($files as $file) {
            $pictures[] = [
                ...$this->images->store($file, $this->directoryFor($car), $storedFiles, $disk),
                'sort_order' => $nextSortOrder++,
            ];
        }

        return $car->pictures()->createMany($pictures);
    }

    private function ensurePhotoLimit(CustomerCar $car, int $incomingCount): void
    {
        $maximum = (int) (config('customer_car_media.max_files') ?? config('images.max_files'));

        if (($car->pictures()->count() + $incomingCount) <= $maximum) {
            return;
        }

        throw new BusinessRuleException(
            messageKey: 'auth.validation.pictures.total_max',
            messageParams: ['max' => $maximum],
            statusCode: 422,
            errors: [
                'pictures' => [__('auth.validation.pictures.total_max', ['max' => $maximum])],
            ],
        );
    }

    private function directoryFor(CustomerCar $car): string
    {
        return "customer-cars/{$car->customer_id}/{$car->id}";
    }
}
