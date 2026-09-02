<?php

namespace App\Http\Controllers\Api\Reference;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferenceResource;
use App\Models\Color;
use Illuminate\Support\Facades\Cache;

class ColorController extends Controller
{
    public function index()
    {
        $colors = Cache::remember('ref:colors', now()->addDay(), fn () =>
            Color::select('id', 'name_en', 'name_ar')->get()
        );

        return $this->success(ReferenceResource::collection($colors));
    }
}