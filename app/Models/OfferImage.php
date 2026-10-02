<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferImage extends Model
{
    protected $table = 'offer_images';

    protected $fillable = ['disk', 'path', 'mime_type', 'size_bytes', 'sort_order'];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'sort_order' => 'integer'];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(OrderOffer::class, 'order_offer_id');
    }

    public function fileMetadata(): array
    {
        return $this->only(['disk', 'path', 'mime_type', 'size_bytes']);
    }
}
