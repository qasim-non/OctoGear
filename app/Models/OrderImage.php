<?php

namespace App\Models;

use App\Services\ImageStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class OrderImage extends Model
{
    protected $fillable = ['disk', 'path', 'mime_type', 'size_bytes', 'sort_order'];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'sort_order' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fileMetadata(): array
    {
        return $this->only(['disk', 'path', 'mime_type', 'size_bytes']);
    }

    public function delete()
    {
        return DB::transaction(function () {
            $deleted = parent::delete();
            if ($deleted) {
                app(ImageStorageService::class)->deleteAfterCommit([$this->fileMetadata()]);
            }

            return $deleted;
        });
    }
}
