<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->success(new AdminProfileResource($request->user()))
            ->header('Cache-Control', 'no-store, private');
    }
}
