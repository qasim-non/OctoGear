<?php

namespace App\Http\Controllers\Api\Reference;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferenceResource;
use App\Models\CarName;
use Illuminate\Support\Facades\Cache;

class CarNameController extends Controller
{
    public function models(CarName $name)
    {
        $models = Cache::remember("ref:name:{$name->id}:models", now()->addDay(), fn () =>
            $name->models()->select('id', 'name_en', 'name_ar')->get()
        );

        return $this->success(ReferenceResource::collection($models));
    }
}