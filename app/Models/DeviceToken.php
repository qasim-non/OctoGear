<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceToken extends Model
{
    /**
     * No soft deletes here — when a token is invalid (user uninstalls),
     * we DELETE it permanently. No need to keep old tokens around.
     */
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'personal_access_token_id',
        'locale',
        'last_seen_at',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'last_seen_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }
}
