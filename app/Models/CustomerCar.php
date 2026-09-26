<?php

namespace App\Models;

use App\Services\CustomerCarPhotoService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCar extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'manufacturing_year',
        'vehicle_plat_number',
        'car_name_id',
        'color_id',
        'customer_id',
        'fuel_type',
        'idempotency_key',
        'idempotency_fingerprint',
    ];

    protected $hidden = [
        'deleted_at',
        'idempotency_key',
        'idempotency_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_year' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function carName(): BelongsTo
    {
        return $this->belongsTo(CarName::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function fuelType(): BelongsTo
    {
        return $this->belongsTo(FuelType::class, 'fuel_type');
    }

    public function pictures(): HasMany
    {
        // Historic records only contained arbitrary client strings and have
        // no trusted private file behind them. The current API exposes only
        // records created by the private-media workflow.
        return $this->hasMany(CustomerCarPicture::class, 'car_id')
            ->whereNotNull('disk')
            ->whereNotNull('path')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    protected static function booted(): void
    {
        // Normal user deletion is a soft delete and intentionally preserves
        // media. An Eloquent instance force delete must remove private files
        // before the database cascade can remove their metadata rows.
        static::forceDeleting(function (self $car): void {
            app(CustomerCarPhotoService::class)->purgeFilesForForceDelete($car);
        });
    }
}
