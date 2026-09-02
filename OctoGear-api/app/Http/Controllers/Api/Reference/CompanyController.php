<?php

namespace App\Http\Controllers\Api\Reference;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferenceResource;
use App\Models\CarCompany;
use App\Models\CarName;
use Illuminate\Support\Facades\Cache;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Cache::remember('ref:companies', now()->addDay(), fn () =>
            CarCompany::select('id', 'name_en', 'name_ar')->get()
        );

        return $this->success(ReferenceResource::collection($companies));
    }

    public function names(CarCompany $company)
    {
        $names = Cache::remember("ref:company:{$company->id}:names", now()->addDay(), fn () =>
            $company->carNames()->select('id', 'name_en', 'name_ar')->get()
        );

        return $this->success(ReferenceResource::collection($names));
    }
}