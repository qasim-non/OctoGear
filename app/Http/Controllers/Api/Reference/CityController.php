<?php

namespace App\Http\Controllers\Api\Reference;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferenceResource;
use App\Models\City;
use Illuminate\Support\Facades\Cache;

class CityController extends Controller
{
    public function index()
    {
        $cities = Cache::remember('ref:cities', now()->addDay(), fn () =>
            City::select('id', 'name_en', 'name_ar')->get()
        );

        return $this->success(ReferenceResource::collection($cities));
    }
}