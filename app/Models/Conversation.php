<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'provider_id',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function unreadMessages(): HasMany
    {
        return $this->messages()->where('is_read', false);
    }

    /**
     * Get the latest message in this conversation.
     * Useful for the conversation list: show last message preview.
     */
    public function latestMessage(): HasMany
    {
        return $this->messages()->latest();
    }

    /**
     * Count of unread messages for a specific user in this conversation.
     */
    public function unreadCountFor(User $user): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->count();
    }

    public function scopeWithLatestMessage($query): void
    {
        $query->with(['latestMessage' => fn ($q) => $q->limit(1)]);
    }

    public function scopeWithUnreadCount($query, User $user): void
    {
        $query->withCount([
            'unreadMessages' => fn ($q) => $q
                ->where('sender_id', '!=', $user->id),
        ]);
    }
}
