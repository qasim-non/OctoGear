<?php

namespace App\Models;

use App\Services\ImageStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCarPicture extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'car_id',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'sort_order',
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

    public function car(): BelongsTo
    {
        return $this->belongsTo(CustomerCar::class, 'car_id');
    }

    public function isStoredMedia(): bool
    {
        return filled($this->disk) && filled($this->path);
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (self $picture): void {
            app(ImageStorageService::class)->delete($picture->getAttributes());
        });
    }
}
