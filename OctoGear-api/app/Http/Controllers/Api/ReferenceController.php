<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferenceResource;
use App\Models\CarCompany;
use App\Models\CarName;
use App\Models\CarSection;
use App\Models\City;
use App\Models\Color;
use App\Models\FuelType;
use Illuminate\Support\Facades\Cache;

class ReferenceController extends Controller
{
    public function cities()
    {
        $cities = Cache::remember('ref:cities', now()->addDay(), fn () =>
            City::select('id', 'name_en', 'name_ar')->get()
        );

        return $this->success(ReferenceResource::collection($cities));
    }

    public function companies()
    {
        $companies = Cache::remember('ref:companies', now()->addDay(), fn () =>
            CarCompany::select('id', 'name_en', 'name_ar')->get()
        );

        return $this->success(ReferenceResource::collection($companies));
    }

    public function companyNames(CarCompany $company)
    {
        $names = Cache::remember("ref:company:{$company->id}:names", now()->addDay(), fn () =>
            $company->carNames()->select('id', 'name_en', 'name_ar')->get()
        );

        return $this->success(ReferenceResource::collection($names));
    }

    public function nameModels(CarName $name)
    {
        $models = Cache::remember("ref:name:{$name->id}:models", now()->addDay(), fn () =>
            $name->models()->select('id', 'name_en', 'name_ar')->get()
        );

        return $this->success(ReferenceResource::collection($models));
    }

    public function fuelTypes()
    {
        $fuelTypes = Cache::remember('ref:fuel-types', now()->addDay(), fn () =>
            FuelType::select('id', 'type_en', 'type_ar')->get()
        );

        return $this->success(ReferenceResource::collection($fuelTypes));
    }

    public function colors()
    {
        $colors = Cache::remember('ref:colors', now()->addDay(), fn () =>
            Color::select('id', 'name_en', 'name_ar')->get()
        );

        return $this->success(ReferenceResource::collection($colors));
    }

    public function sections()
    {
        $sections = Cache::remember('ref:sections', now()->addDay(), fn () =>
            CarSection::select('id', 'name_en', 'name_ar')
                ->get()
        );

        return $this->success(ReferenceResource::collection($sections));
    }

    public function sectionComponents(CarSection $section)
    {
        $components = Cache::remember("ref:section:{$section->id}:components", now()->addDay(), fn () =>
            $section->components()
                ->select('id', 'name_en', 'name_ar', 'section_id')
                ->get()
        );

        return $this->success(ReferenceResource::collection($components));
    }
}
