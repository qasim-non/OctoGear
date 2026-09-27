<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StorePicture;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class StoreMediaService
{
    public function __construct(private ImageStorageService $images, private StoreCarService $cars) {}

    /**
     * An omitted gallery is unchanged; a supplied gallery replaces its files.
     * Keep the old files until the replacement and its metadata are committed.
     */
    public function update(Store $store, array $data): Store
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($store, $data, &$storedFiles) {
                $store = Store::query()->lockForUpdate()->findOrFail($store->getKey());
                $attributes = Arr::except($data, ['pictures', 'commercial_registration_picture']);
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

                if (array_key_exists('pictures', $data)) {
                    $oldPictures = $store->pictures()->get();

                    foreach ($oldPictures as $picture) {
                        $obsoleteFiles[] = $picture->image();
                    }

                    $store->pictures()->delete();

                    foreach ($data['pictures'] as $sortOrder => $file) {
                        $store->pictures()->create([
                            ...$this->images->store($file, "stores/{$store->id}/pictures", $storedFiles),
                            'sort_order' => $sortOrder,
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
