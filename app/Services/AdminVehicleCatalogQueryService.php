<?php

namespace App\Services;

use App\Models\CarCompany;
use App\Models\CarName;
use App\Models\Country;
use App\Models\CustomerCar;
use App\Models\StoreCompany;
use App\Models\StoreRequest;
use App\Models\StoresCar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminVehicleCatalogQueryService
{
    public function companies(array $filters): LengthAwarePaginator
    {
        return $this->searchNames($this->companyQuery(), $filters)
            ->addSelect('country_id')->with('country:id,name_en,name_ar')
            ->withCount('carNames')->paginate(25);
    }

    public function companyOptions(): Collection
    {
        return $this->companyQuery()->get();
    }

    public function names(array $filters): LengthAwarePaginator
    {
        return $this->searchNames(CarName::query(), $filters)
            ->select('id', 'name_en', 'name_ar', 'car_company_id')
            ->whereHas('carCompany')
            ->when($filters['company_id'] ?? null, fn (Builder $query, int $id) => $query->where('car_company_id', $id))
            ->with(['carCompany:id,name_en,name_ar,country_id', 'carCompany.country:id,name_en,name_ar'])
            ->orderBy('name_en')->orderBy('id')->paginate(25);
    }

    private function companyQuery(): Builder
    {
        return CarCompany::query()->select('id', 'name_en', 'name_ar')->orderBy('name_en')->orderBy('id');
    }

    private function searchNames(Builder $query, array $filters): Builder
    {
        $search = $filters['search'] ?? '';
        if ($search !== '') {
            $query->where(fn (Builder $names) => $names->where('name_en', 'like', "%{$search}%")
                ->orWhere('name_ar', 'like', "%{$search}%"));
        }

        return $query;
    }

    public function inUse(CarCompany|CarName $record): bool
    {
        if ($record instanceof CarCompany) {
            return $record->carNames()->exists()
                || CarName::withTrashed()->where('car_company_id', $record->id)
                    ->where(fn (Builder $names) => $names
                        ->whereHas('customerCars', fn (Builder $cars) => $cars->withTrashed())
                        ->orWhereHas('storesCars', fn (Builder $cars) => $cars->withTrashed()))->exists()
                || StoreCompany::withTrashed()->where('company_id', $record->id)->exists()
                || StoreRequest::withTrashed()->where(fn (Builder $requests) => $requests
                    ->whereJsonContains('company_ids', $record->id)
                    ->orWhereJsonContains('previous_details->company_ids', $record->id))->exists()
                || DB::table('order_vehicle_details')->where('car_company_id', $record->id)
                    ->orWhereIn('car_name_id', CarName::withTrashed()->select('id')->where('car_company_id', $record->id))->exists();
        }

        return CustomerCar::withTrashed()->where('car_name_id', $record->id)->exists()
            || StoresCar::withTrashed()->where('car_name_id', $record->id)->exists()
            || DB::table('order_vehicle_details')->where('car_name_id', $record->id)->exists();
    }

    public function countryOptions(): Collection
    {
        return Country::query()->select('id', 'name_en', 'name_ar')->orderBy('name_en')->orderBy('id')->get();
    }
}
