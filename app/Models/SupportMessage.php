<?php

namespace App\Models;

use App\Enums\SupportActor;
use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sender_kind' => SupportActor::class];
    }
}
