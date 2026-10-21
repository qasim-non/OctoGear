<?php

namespace App\Models;

use App\Enums\SupportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => SupportStatus::class, 'version' => 'integer',
            'last_activity_at' => 'datetime', 'closed_at' => 'datetime', 'reopened_at' => 'datetime',
            'user_read_through_id' => 'integer', 'support_read_through_id' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function latestMessage(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'last_message_id');
    }
}
