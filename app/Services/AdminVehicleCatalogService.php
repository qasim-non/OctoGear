<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\CarCompany;
use App\Models\CarName;
use App\Support\VehicleCatalogVersion;
use Illuminate\Support\Facades\DB;

class AdminVehicleCatalogService
{
    public function __construct(private AdminVehicleCatalogQueryService $queries) {}

    public function save(string $type, array $data, ?int $id = null): CarCompany|CarName
    {
        return DB::transaction(function () use ($type, $data, $id) {
            $this->lockCatalog();
            $record = $id ? $type::query()->lockForUpdate()->findOrFail($id) : new $type;
            if ($id) {
                $this->checkVersion($record, $data['version']);
            }
            if ($record instanceof CarName) {
                CarCompany::query()->lockForUpdate()->findOrFail($data['car_company_id']);
                // Moving a model would reinterpret all vehicles already assigned to it.
                if ($id && $record->car_company_id !== (int) $data['car_company_id']) {
                    throw new BusinessRuleException(messageKey: 'admin_catalog.company_fixed', statusCode: 422,
                        errors: ['car_company_id' => [__('admin_catalog.company_fixed')]]);
                }
            }
            foreach (['name_en', 'name_ar'] as $field) {
                $query = $type::query()->whereRaw('LOWER('.$field.') = ?', [mb_strtolower(trim($data[$field]))]);
                if ($record instanceof CarName) {
                    $query->where('car_company_id', $data['car_company_id']);
                }
                if ($id) {
                    $query->whereKeyNot($id);
                }
                if ($query->exists()) {
                    throw new BusinessRuleException(messageKey: 'admin_catalog.duplicate', statusCode: 422,
                        errors: [$field => [__('admin_catalog.duplicate')]]);
                }
            }
            unset($data['version']);
            $record->fill($data)->save();

            return $record instanceof CarCompany
                ? $record->load('country')->loadCount('carNames')
                : $record->load('carCompany.country');
        });
    }

    public function delete(string $type, int $id, string $version): void
    {
        DB::transaction(function () use ($type, $id, $version) {
            $this->lockCatalog();
            $record = $type::query()->lockForUpdate()->findOrFail($id);
            $this->checkVersion($record, $version);
            if ($this->queries->inUse($record)) {
                throw new BusinessRuleException(messageKey: 'admin_catalog.in_use', statusCode: 409);
            }
            $record->delete();
        });
    }

    private function lockCatalog(): void
    {
        DB::table('vehicle_catalog_locks')->where('id', 1)->lockForUpdate()->sole();
    }

    private function checkVersion(CarCompany|CarName $record, string $version): void
    {
        if (! hash_equals(VehicleCatalogVersion::for($record), $version)) {
            throw new BusinessRuleException(messageKey: 'admin_catalog.stale', statusCode: 409);
        }
    }
}
