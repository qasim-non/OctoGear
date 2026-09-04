<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoresCar;
use Illuminate\Support\Facades\DB;

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
    /**
     * Create a store car with its sections and pictures.
     */
    public function create(Store $store, array $data): StoresCar
    {
        return DB::transaction(function () use ($store, $data) {
            $car = $store->cars()->create(collect($data)->except(['pictures', 'sections'])->all());

            $this->syncSections($car, $data['sections'] ?? []);

            if (! empty($data['pictures'])) {
                $car->pictures()->createMany(
                    array_map(fn ($picture) => ['picture' => $picture], $data['pictures'])
                );
            }

            return $car;
        });
    }

    /**
     * Update a store car and, when provided, replace its sections/pictures.
     */
    public function update(StoresCar $car, array $data): StoresCar
    {
        DB::transaction(function () use ($car, $data) {
            $car->update(collect($data)->except(['pictures', 'sections'])->all());

            if (array_key_exists('sections', $data)) {
                $this->syncSections($car, $data['sections'] ?? []);
            }

            if (array_key_exists('pictures', $data)) {
                $car->pictures()->delete();

                $car->pictures()->createMany(
                    array_map(fn ($picture) => ['picture' => $picture], $data['pictures'])
                );
            }
        });

        return $car;
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
