<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreRatingRequest;
use App\Http\Resources\RatingResource;
use App\Models\Rating;

class CustomerRatingController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Rating::class);

        $ratings = auth()->user()
            ->ratings()
            ->with('store')
            ->latest()
            ->paginate(15);

        return $this->paginated($ratings->through(fn ($rating) => new RatingResource($rating)));
    }

    public function store(StoreRatingRequest $request)
    {
        $this->authorize('create', Rating::class);

        $rating = auth()->user()
            ->ratings()
            ->create([
                ...$request->validated(),
            ]);

        $rating->load('store');

        return $this->created(new RatingResource($rating));
    }
}
