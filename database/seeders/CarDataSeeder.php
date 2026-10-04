<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedRecords;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CarDataSeeder extends Seeder
{
    public function run(): void
    {
        $snapshot = $this->read('vehicles-vpic.json');
        $regional = $this->read('vehicles-regional.json');
        $history = $this->read('vehicles-vpic-history.json');
        $saudi = $this->read('vehicles-saudi.json');
        $arabic = $this->read('model-arabic.json');
        $makes = $this->merge([...$snapshot['makes'], ...$history['makes'], ...$regional['makes'], ...$saudi['makes']], $arabic);

        DB::transaction(function () use ($makes): void {
            foreach ($makes as $make) {
                $countryId = DB::table('countries')->where('name_en', $make['country'])->value('id');
                if (! $countryId) {
                    throw new RuntimeException("Seed countries before vehicles: {$make['country']}");
                }
                $companyId = SeedRecords::reference('cars_companies', ['name_en' => $make['name_en']], [
                    'name_ar' => $make['name_ar'], 'country_id' => $countryId,
                ]);
                foreach ($make['models'] as $model) {
                    $name = $model['name_en'];
                    $translation = $model['name_ar'];
                    $carNameId = SeedRecords::reference('cars_names', ['car_company_id' => $companyId, 'name_en' => $name], ['name_ar' => $translation]);
                    // Only NHTSA's explicitly returned model year is asserted.
                    // Regional pages verify a nameplate, not its entire year/trim history.
                    foreach (array_keys($model['suffixes']) as $suffix) {
                        SeedRecords::reference('models', ['car_name_id' => $carNameId, 'name_en' => $name.$suffix], ['name_ar' => $translation.$suffix]);
                    }
                }
            }
        });

        $this->command?->info('Seeded '.count($makes).' marques and '.array_sum(array_map(fn ($make) => count($make['models']), $makes)).' car names.');
    }

    /** Merge before writing, so overlapping sources never toggle translations on reruns. */
    private function merge(array $sources, array $arabic): array
    {
        $makes = [];
        foreach ($sources as $source) {
            $key = mb_strtolower(trim($source['name_en']));
            $makes[$key] ??= [...$source, 'models' => []];
            if ($makes[$key]['country'] !== $source['country']) {
                throw new RuntimeException('Conflicting marque origin: '.$source['name_en']);
            }
            foreach ($source['models'] as $model) {
                $name = trim(is_array($model) ? $model[0] : $model);
                if ($name === '') {
                    throw new RuntimeException('Empty car name in '.$source['name_en']);
                }
                $modelKey = mb_strtolower($name);
                $translation = is_array($model) ? $model[1] : ($arabic[$name] ?? null);
                $makes[$key]['models'][$modelKey] ??= ['name_en' => $name, 'name_ar' => $translation ?? $name, 'suffixes' => []];
                if ($translation !== null) {
                    $makes[$key]['models'][$modelKey]['name_ar'] = $translation;
                }
                $suffix = isset($source['year']) ? ' '.$source['year'] : '';
                $makes[$key]['models'][$modelKey]['suffixes'][$suffix] = true;
            }
        }

        return $makes;
    }

    private function read(string $file): array
    {
        return json_decode(file_get_contents(__DIR__.'/data/'.$file), true, flags: JSON_THROW_ON_ERROR);
    }
}
