<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['token_hash', 'retry_response'];

    protected function casts(): array
    {
        return ['used_at' => 'immutable_datetime', 'retry_expires_at' => 'immutable_datetime', 'retry_response' => 'encrypted:array'];
    }
}
