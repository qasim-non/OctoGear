<?php

namespace App\Http\Controllers\Api\Reference;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reference\ReferenceCatalogRequest;
use App\Http\Resources\ReferenceResource;
use App\Models\City;

class CityController extends Controller
{
    public function index(ReferenceCatalogRequest $request)
    {
        $filters = $request->validated();
        $cities = City::query()
            ->select('id', 'name_en', 'name_ar')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($names) => $names->where('name_en', 'like', "%{$search}%")
                ->orWhere('name_ar', 'like', "%{$search}%")
            ))
            ->orderBy('name_en')->orderBy('id')
            ->paginate($filters['per_page'] ?? 20);

        return $this->paginated($cities->through(fn ($city) => (new ReferenceResource($city))->resolve($request)));
    }
}
