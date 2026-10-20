<?php

namespace App\Http\Controllers\Api\Reference;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reference\ReferenceCatalogRequest;
use App\Http\Resources\ReferenceResource;
use App\Models\City;
use Illuminate\Database\Eloquent\Builder;

class CityController extends Controller
{
    public function index(ReferenceCatalogRequest $request)
    {
        $filters = $request->validated();
        $cities = $this->catalogQuery()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($names) => $names->where('name_en', 'like', "%{$search}%")
                ->orWhere('name_ar', 'like', "%{$search}%")
            ))
            ->paginate($filters['per_page'] ?? 20);

        return $this->paginated($cities->through(fn ($city) => (new ReferenceResource($city))->resolve($request)));
    }

    public function options()
    {
        return $this->success($this->catalogQuery()->get())
            ->header('Cache-Control', 'no-store, private');
    }

    private function catalogQuery(): Builder
    {
        return City::query()->select('id', 'name_en', 'name_ar')->orderBy('name_en')->orderBy('id');
    }
}
