<?php

namespace App\Http\Controllers\Api\Reference;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferenceResource;
use App\Models\FuelType;
use Illuminate\Support\Facades\Cache;

class FuelTypeController extends Controller
{
    public function index()
    {
        $fuelTypes = Cache::remember('ref:fuel-types', now()->addDay(), fn () =>
            FuelType::select('id', 'type_en', 'type_ar')->get()
        );

        return $this->success(ReferenceResource::collection($fuelTypes));
    }
}