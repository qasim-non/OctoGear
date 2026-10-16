<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StorePicture;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreMediaService
{
    public function __construct(private ImageStorageService $images, private StoreCarService $cars) {}

    /**
     * Omitted gallery fields preserve files. Retained IDs keep selected photos;
     * uploads alone preserve the legacy full-replacement contract.
     * Keep the old files until the replacement and its metadata are committed.
     */
    public function update(Store $store, array $data): Store
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($store, $data, &$storedFiles) {
                $store = Store::query()->lockForUpdate()->findOrFail($store->getKey());
                $attributes = Arr::except($data, ['pictures', 'retained_picture_ids', 'company_ids', 'commercial_registration_picture']);
                $obsoleteFiles = [];

                if (isset($data['commercial_registration_picture'])) {
                    $obsoleteFiles[] = $store->registrationImage();
                    $image = $this->images->store(
                        $data['commercial_registration_picture'],
                        "stores/{$store->id}/registration",
                        $storedFiles,
                    );
                    $attributes = [...$attributes, ...self::registrationAttributes($image)];
                }

                $store->update($attributes);

                if (array_key_exists('company_ids', $data)) {
                    $store->companies()->sync($data['company_ids']);
                }

                if (array_key_exists('pictures', $data) || array_key_exists('retained_picture_ids', $data)) {
                    $oldPictures = $store->pictures()->get()->keyBy('id');
                    $retained = array_map('intval', $data['retained_picture_ids'] ?? []);
                    $files = $data['pictures'] ?? [];
                    // Recheck after locking: validation may have raced another edit.
                    if (array_diff($retained, $oldPictures->keys()->all()) || count($retained) + count($files) > config('images.max_files')) {
                        throw ValidationException::withMessages(['pictures' => [__('validation.max.array', ['attribute' => 'pictures', 'max' => config('images.max_files')])]]);
                    }
                    foreach ($oldPictures as $picture) {
                        if (! in_array($picture->id, $retained, true)) {
                            $obsoleteFiles[] = $picture->image();
                            $picture->delete();
                        }
                    }
                    foreach ($retained as $sortOrder => $id) {
                        $oldPictures[$id]->update(['sort_order' => $sortOrder]);
                    }
                    foreach (array_values($files) as $sortOrder => $file) {
                        $store->pictures()->create([
                            ...$this->images->store($file, "stores/{$store->id}/pictures", $storedFiles),
                            'sort_order' => count($retained) + $sortOrder,
                        ]);
                    }
                }

                $this->images->deleteAfterCommit($obsoleteFiles);

                return $store;
            });
        } catch (Throwable $exception) {
            $this->images->cleanup($storedFiles);

            throw $exception;
        }
    }

    public function purgeFilesForForceDelete(Store $store): void
    {
        $this->images->delete($store->registrationImage());

        foreach (StorePicture::withTrashed()->where('store_id', $store->id)->get() as $picture) {
            $this->images->delete($picture->image());
        }

        foreach ($store->cars()->withTrashed()->get() as $car) {
            $this->cars->purgeFilesForForceDelete($car);
        }
    }

    public static function registrationAttributes(array $image): array
    {
        return [
            'commercial_registration_disk' => $image['disk'],
            'commercial_registration_path' => $image['path'],
            'commercial_registration_mime_type' => $image['mime_type'],
            'commercial_registration_size_bytes' => $image['size_bytes'],
        ];
    }
}
