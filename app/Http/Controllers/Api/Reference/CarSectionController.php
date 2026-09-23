<?php

namespace App\Http\Controllers\Api\Reference;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferenceResource;
use App\Models\CarSection;
use Illuminate\Support\Facades\Cache;

class CarSectionController extends Controller
{
    public function index()
    {
        $sections = Cache::remember('ref:sections', now()->addDay(), fn () =>
            CarSection::select('id', 'name_en', 'name_ar')->get()
        );

        return $this->success(ReferenceResource::collection($sections));
    }

    public function components(CarSection $section)
    {
        $components = Cache::remember("ref:section:{$section->id}:components", now()->addDay(), fn () =>
            $section->components()
                ->select('id', 'name_en', 'name_ar', 'section_id')
                ->get()
        );

        return $this->success(ReferenceResource::collection($components));
    }
}