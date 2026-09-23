<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
