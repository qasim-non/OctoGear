<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\CustomerCar;
use App\Models\CustomerCarPicture;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Owns private file storage for customer-car photos. The database stores only
 * a disk and relative path; clients receive an authenticated API route.
 */
class CustomerCarPhotoService
{
    public function __construct(private FilesystemFactory $filesystems) {}

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
        $storage = $this->filesystems->disk($picture->disk);

        if (! $storage->exists($picture->path)) {
            return null;
        }

        return $storage->response(
            $picture->path,
            'car-photo.'.$this->extensionFor($picture),
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    /**
     * Hide the photo immediately by soft deleting its row, then remove the
     * physical private file. A cleanup failure leaves an inaccessible,
     * soft-deleted row rather than resurrecting a public/private reference.
     */
    public function delete(CustomerCarPicture $picture): void
    {
        $disk = $picture->disk;
        $path = $picture->path;

        DB::transaction(function () use ($picture) {
            $picture->delete();
        });

        try {
            $storage = $this->filesystems->disk($disk);

            if ($storage->exists($path)) {
                $storage->delete($path);
            }

            $picture->forceDelete();
        } catch (Throwable $exception) {
            report($exception);
        }
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
            $this->deleteFileOrAbort((string) $picture->disk, (string) $picture->path);
        }
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $storedFiles
     */
    public function cleanupStoredFiles(array $storedFiles): void
    {
        foreach ($storedFiles as $file) {
            try {
                $this->filesystems->disk($file['disk'])->delete($file['path']);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
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
            $path = $file->store($this->directoryFor($car), $disk);

            if ($path === false) {
                throw new RuntimeException('Unable to store customer-car media.');
            }

            $storedFiles[] = ['disk' => $disk, 'path' => $path];

            $pictures[] = [
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'sort_order' => $nextSortOrder++,
            ];
        }

        return $car->pictures()->createMany($pictures);
    }

    private function ensurePhotoLimit(CustomerCar $car, int $incomingCount): void
    {
        $maximum = (int) config('customer_car_media.max_files');

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

    private function deleteFileOrAbort(string $disk, string $path): void
    {
        try {
            $storage = $this->filesystems->disk($disk);

            if ($storage->exists($path) && ! $storage->delete($path)) {
                throw new RuntimeException('Private media deletion returned false.');
            }
        } catch (Throwable) {
            // Do not surface or log a storage path/disk name. The caller must
            // keep the database rows intact so an operator can retry safely.
            throw new BusinessRuleException(
                message: __('auth.general.media_cleanup_unavailable'),
                messageKey: 'auth.general.media_cleanup_unavailable',
                statusCode: 503,
            );
        }
    }

    private function directoryFor(CustomerCar $car): string
    {
        return "customer-cars/{$car->customer_id}/{$car->id}";
    }

    private function extensionFor(CustomerCarPicture $picture): string
    {
        return match ($picture->mime_type) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'image',
        };
    }
}
