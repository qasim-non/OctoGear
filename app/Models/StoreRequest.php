<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\HasRegistrationImage;
use App\Services\ImageStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreRequest extends Model
{
    use HasFactory, HasRegistrationImage, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'mobile',
        'nick_name',
        'employee_name',
        'url_location',
        'commercial_registration_number',
        'commercial_registration_disk',
        'commercial_registration_path',
        'commercial_registration_mime_type',
        'commercial_registration_size_bytes',
        'city_id',
        'request_status',
        'rejection_reason',
        'processed_by',
    ];

    protected $hidden = [
        'deleted_at',
        'commercial_registration_disk',
        'commercial_registration_path',
    ];

    protected function casts(): array
    {
        return [
            'request_status' => RequestStatus::class,
            'commercial_registration_size_bytes' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (self $storeRequest): void {
            app(ImageStorageService::class)->delete($storeRequest->registrationImage());
        });
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
