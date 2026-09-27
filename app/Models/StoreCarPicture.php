<?php

namespace App\Models;

use App\Services\ImageStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreCarPicture extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'sort_order',
        'car_id',
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
        return $this->belongsTo(StoresCar::class, 'car_id');
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (self $picture): void {
            app(ImageStorageService::class)->delete($picture->getAttributes());
        });
    }
}
