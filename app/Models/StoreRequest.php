<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Models\Concerns\HasRegistrationImage;
use App\Services\ImageStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreRequest extends Model
{
    use HasFactory, HasRegistrationImage, SoftDeletes;

    protected $fillable = [
        'store_id', 'pending_store_id', 'change_reason', 'previous_details', 'base_version', 'processed_at',
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
        'company_ids',
        'request_status',
        'rejection_reason',
        'processed_by',
        'idempotency_key',
        'idempotency_fingerprint',
    ];

    protected $hidden = [
        'deleted_at',
        'idempotency_key',
        'idempotency_fingerprint',
        'commercial_registration_disk',
        'commercial_registration_path',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
            'previous_details' => 'array',
            'processed_at' => 'datetime',
            'request_status' => RequestStatus::class,
            'company_ids' => 'array',
            'commercial_registration_size_bytes' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function pictures(): HasMany
    {
        return $this->hasMany(StoreRequestPicture::class)->orderBy('sort_order')->orderBy('id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (self $storeRequest): void {
            app(ImageStorageService::class)->delete($storeRequest->registrationImage());
            $storeRequest->pictures->each->delete();
        });
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
