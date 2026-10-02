<?php

namespace App\Http\Controllers\Api\Reference;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reference\ReferenceCatalogRequest;
use App\Http\Resources\ReferenceResource;
use App\Models\CarName;

class CarNameController extends Controller
{
    public function models(ReferenceCatalogRequest $request, CarName $name)
    {
        $filters = $request->validated();
        $models = $name->models()
            ->select('id', 'name_en', 'name_ar')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($names) => $names->where('name_en', 'like', "%{$search}%")
                ->orWhere('name_ar', 'like', "%{$search}%")
            ))
            ->orderBy('name_en')->orderBy('id')
            ->paginate($filters['per_page'] ?? 20);

        return $this->paginated($models->through(fn ($model) => (new ReferenceResource($model))->resolve($request)));
    }
}
