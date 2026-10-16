<?php

namespace App\Models;

use App\Services\ImageStorageService;
use Illuminate\Database\Eloquent\Model;

class StoreRequestPicture extends Model
{
    protected $fillable = ['disk', 'path', 'mime_type', 'size_bytes', 'sort_order'];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'sort_order' => 'integer'];
    }

    public function image(): array
    {
        return $this->only(['disk', 'path', 'mime_type', 'size_bytes']);
    }

    protected static function booted(): void
    {
        static::deleting(fn (self $picture) => app(ImageStorageService::class)->delete($picture->image()));
    }
}
