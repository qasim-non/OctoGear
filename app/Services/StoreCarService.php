<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreCarPicture;
use App\Models\StoresCar;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Owns the store-car persistence workflow (a car plus its section report and
 * pictures, created/updated atomically).
 *
 * Both the create and update paths replace the section report and picture set
 * as part of a single transaction so a partial failure can never leave the
 * car in an inconsistent state.
 */
class StoreCarService
{
    public function __construct(private ImageStorageService $images) {}

    /**
     * Create a store car with its sections and pictures.
     */
    public function create(Store $store, array $data): StoresCar
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($store, $data, &$storedFiles) {
                $car = $store->cars()->create(collect($data)->except(['pictures', 'sections'])->all());

                $this->syncSections($car, $data['sections'] ?? []);
                $this->storePictures($car, $data['pictures'] ?? [], $storedFiles);

                return $car;
            });
        } catch (Throwable $exception) {
            $this->images->cleanup($storedFiles);

            throw $exception;
        }
    }

    /**
     * Update a store car and, when provided, replace its sections/pictures.
     */
    public function update(StoresCar $car, array $data): StoresCar
    {
        $storedFiles = [];

        try {
            DB::transaction(function () use ($car, $data, &$storedFiles) {
                $lockedCar = StoresCar::query()->lockForUpdate()->findOrFail($car->getKey());
                $lockedCar->update(collect($data)->except(['pictures', 'sections'])->all());

                if (array_key_exists('sections', $data)) {
                    $this->syncSections($lockedCar, $data['sections'] ?? []);
                }

                if (array_key_exists('pictures', $data)) {
                    $oldPictures = $lockedCar->pictures()->get();
                    $lockedCar->pictures()->delete();
                    $this->storePictures($lockedCar, $data['pictures'] ?? [], $storedFiles);

                    // Commit the replacement and durable cleanup references
                    // together, then remove old files through the shared retry flow.
                    $this->images->deleteAfterCommit(
                        $oldPictures->map(fn (StoreCarPicture $picture) => $picture->getAttributes())->all(),
                    );
                }
            });
        } catch (Throwable $exception) {
            $this->images->cleanup($storedFiles);

            throw $exception;
        }

        return $car->refresh();
    }

    public function purgeFilesForForceDelete(StoresCar $car): void
    {
        $pictures = StoreCarPicture::query()->withTrashed()
            ->where('car_id', $car->getKey())
            ->whereNotNull('disk')->whereNotNull('path')->get();

        foreach ($pictures as $picture) {
            $this->images->delete($picture->getAttributes());
        }
    }

    private function storePictures(StoresCar $car, array $files, array &$storedFiles): void
    {
        foreach (array_values($files) as $sortOrder => $file) {
            $metadata = $this->images->store(
                $file,
                "store-cars/{$car->store_id}/{$car->id}",
                $storedFiles,
            );

            $car->pictures()->create([...$metadata, 'sort_order' => $sortOrder]);
        }
    }

    /**
     * Replace the car's section-condition report with the given sections.
     */
    private function syncSections(StoresCar $car, array $sections): void
    {
        $car->storeCarSections()->delete();

        $car->storeCarSections()->createMany(
            array_map(fn ($section) => [
                'section_id' => $section['section_id'],
                'condition' => $section['condition'],
            ], $sections)
        );
    }
}
