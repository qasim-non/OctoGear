<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreRatingRequest;
use App\Http\Resources\RatingResource;
use App\Models\Rating;

class RatingController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Rating::class);

        $user = auth()->user();

        if ($user->isProvider()) {
            $storeIds = $user->stores()->pluck('id');

            $ratings = Rating::query()
                ->whereIn('store_id', $storeIds)
                ->with('store')
                ->latest()
                ->paginate(15);
        } else {
            $ratings = $user->ratings()
                ->with('store')
                ->latest()
                ->paginate(15);
        }

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
