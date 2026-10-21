<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PreferredLanguage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\UpdateLanguageRequest;
use App\Http\Resources\AdminProfileResource;
use App\Services\UserPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProfileController extends Controller
{
    public function updateLanguage(UpdateLanguageRequest $request, UserPreferenceService $preferences): JsonResponse
    {
        $language = $request->enum('preferred_language', PreferredLanguage::class);
        $preferences->updateLanguage($request->user(), $language);

        return $this->success(['preferred_language' => $language->value])
            ->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request): JsonResponse
    {
        return $this->success(new AdminProfileResource($request->user()))
            ->header('Cache-Control', 'no-store, private');
    }
}
