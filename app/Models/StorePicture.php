<?php

namespace App\Models;

use App\Services\ImageStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorePicture extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'sort_order',
        'store_id',
    ];

    protected $hidden = [
        'deleted_at',
        'disk',
        'path',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function image(): array
    {
        return $this->only(['disk', 'path', 'mime_type', 'size_bytes']);
    }

    public function isStoredMedia(): bool
    {
        return filled($this->disk) && filled($this->path);
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (self $picture): void {
            app(ImageStorageService::class)->delete($picture->image());
        });
    }
}
