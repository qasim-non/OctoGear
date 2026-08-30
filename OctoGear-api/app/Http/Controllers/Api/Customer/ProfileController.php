<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateProfileRequest;
use App\Http\Resources\UserResource;

class ProfileController extends Controller
{
    public function show()
    {
        $user = auth()->user()->load('city');

        return $this->success(new UserResource($user));
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = auth()->user();
        $user->update($request->validated());

        $user->load('city');

        return $this->success(new UserResource($user));
    }
}
