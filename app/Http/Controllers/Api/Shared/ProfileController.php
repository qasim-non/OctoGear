<?php

namespace App\Http\Controllers\Api\Shared;

use App\Enums\PreferredLanguage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\UpdateLanguageRequest;
use App\Http\Resources\UserResource;
use App\Services\UserPreferenceService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function updateLanguage(UpdateLanguageRequest $request, UserPreferenceService $preferences)
    {
        $language = $request->enum('preferred_language', PreferredLanguage::class);
        $preferences->updateLanguage($request->user(), $language);

        return $this->success(['preferred_language' => $language->value]);
    }

    public function show(Request $request)
    {
        return $this->success(new UserResource($request->user()->load('city')));
    }
}
