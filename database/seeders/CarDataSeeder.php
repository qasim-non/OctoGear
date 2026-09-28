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
        $arabic = $this->read('model-arabic.json');
        $makes = [...$snapshot['makes'], ...$regional['makes']];

        DB::transaction(function () use ($makes, $arabic): void {
            foreach ($makes as $make) {
                $countryId = DB::table('countries')->where('name_en', $make['country'])->value('id');
                if (! $countryId) {
                    throw new RuntimeException("Seed countries before vehicles: {$make['country']}");
                }
                $companyId = SeedRecords::reference('cars_companies', ['name_en' => $make['name_en']], [
                    'name_ar' => $make['name_ar'], 'country_id' => $countryId,
                ]);
                foreach ($make['models'] as $model) {
                    $name = is_array($model) ? $model[0] : $model;
                    $translation = is_array($model) ? $model[1] : ($arabic[$name] ?? $name);
                    $carNameId = SeedRecords::reference('cars_names', ['car_company_id' => $companyId, 'name_en' => $name], ['name_ar' => $translation]);
                    // Only NHTSA's explicitly returned model year is asserted.
                    // Regional pages verify a nameplate, not its entire year/trim history.
                    $suffix = isset($make['year']) ? ' '.$make['year'] : '';
                    SeedRecords::reference('models', ['car_name_id' => $carNameId, 'name_en' => $name.$suffix], ['name_ar' => $translation.$suffix]);
                }
            }
        });
    }

    private function read(string $file): array
    {
        return json_decode(file_get_contents(__DIR__.'/data/'.$file), true, flags: JSON_THROW_ON_ERROR);
    }
}
