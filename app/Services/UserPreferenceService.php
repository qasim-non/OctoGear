<?php

namespace App\Services;

use App\Enums\PreferredLanguage;
use App\Models\Admin;
use App\Models\User;

class UserPreferenceService
{
    public function updateLanguage(User|Admin $user, PreferredLanguage $language): void
    {
        $user->preferred_language = $language;
        if ($user->isDirty('preferred_language')) {
            $user->save();
        }
    }
}
