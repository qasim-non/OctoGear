<?php

namespace App\Http\Controllers\Api\Reference;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reference\ReferenceCatalogRequest;
use App\Http\Resources\ReferenceResource;
use App\Models\CarSection;
use Illuminate\Support\Facades\Cache;

class CarSectionController extends Controller
{
    public function index()
    {
        $sections = Cache::remember('ref:sections', now()->addDay(), fn () => CarSection::select('id', 'name_en', 'name_ar')->get()
        );

        return $this->success(ReferenceResource::collection($sections));
    }

    public function components(ReferenceCatalogRequest $request, CarSection $section)
    {
        $filters = $request->validated();
        $components = $section->components()
            ->select('id', 'name_en', 'name_ar', 'section_id')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($names) => $names->where('name_en', 'like', "%{$search}%")
                ->orWhere('name_ar', 'like', "%{$search}%")
            ))
            ->orderBy('name_en')->orderBy('id')
            ->paginate($filters['per_page'] ?? 20);

        return $this->paginated($components->through(fn ($component) => (new ReferenceResource($component))->resolve($request)));
    }
}
