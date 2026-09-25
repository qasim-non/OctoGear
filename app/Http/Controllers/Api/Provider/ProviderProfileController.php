<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\UpdateProviderProfileRequest;
use App\Http\Resources\UserResource;

class ProviderProfileController extends Controller
{
    public function update(UpdateProviderProfileRequest $request)
    {
        $user = auth()->user();
        $user->update($request->validated());

        $user->load('city');

        return $this->success(new UserResource($user));
    }
}
