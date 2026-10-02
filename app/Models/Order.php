<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Services\ImageStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;
    use SoftDeletes {
        forceDelete as private forceDeleteRecord;
    }

    protected $fillable = [
        'order_type',
        'quantity',
        'status',
        'offered_price',
        'requested_unit_price',
        'notes',
        'customer_id',
        'store_car_component_id',
        'component_id',
        'component_name',
        'accepted_store_id',
        'accepted_offer_id',
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
            'order_type' => OrderType::class,
            'status' => OrderStatus::class,
            'quantity' => 'integer',
            'offered_price' => 'integer',
            'requested_unit_price' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function forceDelete()
    {
        // Retain cleanup jobs atomically with deletion; remove physical files
        // only after commit. Storage failures can then be retried safely.
        return DB::transaction(function () {
            $files = $this->images()->get()->map->fileMetadata()->all();
            $deleted = $this->forceDeleteRecord();
            if ($deleted) {
                app(ImageStorageService::class)->deleteAfterCommit($files);
            }

            return $deleted;
        });
    }

    public function images(): HasMany
    {
        return $this->hasMany(OrderImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /*
     |--------------------------------------------------------------------------
     | RELATIONSHIPS
     |--------------------------------------------------------------------------
     |
     | Order flow:
     |
     | SPECIFIC order:
     |   Order → storeCarComponent → storeCar → Store (direct chain)
     |
     | GENERAL order:
     |   Order → vehicleDetails (submission-time vehicle snapshot)
     |   Order → offers (multiple stores bid on it)
     */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * The specific store car component this order targets.
     * NULL for general orders (no store selected yet).
     */
    public function storeCarComponent(): BelongsTo
    {
        return $this->belongsTo(StoreCarComponent::class, 'store_car_component_id');
    }

    public function vehicleDetails(): HasOne
    {
        return $this->hasOne(OrderVehicleDetail::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class)->withTrashed();
    }

    public function requestedComponentName(string $locale): ?string
    {
        return $this->component_id === null
            ? $this->component_name
            : $this->component?->{$locale === 'en' ? 'name_en' : 'name_ar'};
    }

    /**
     * Convenience: get the store that owns this order's component.
     * Chain: order → storeCarComponent → storeCar → store
     * NULL for general orders.
     */
    public function getStoreAttribute(): ?Store
    {
        return $this->storeCarComponent?->storeCar?->store;
    }

    public function offers(): HasMany
    {
        return $this->hasMany(OrderOffer::class);
    }

    public function acceptedStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'accepted_store_id');
    }

    public function acceptedOffer(): BelongsTo
    {
        return $this->belongsTo(OrderOffer::class, 'accepted_offer_id')->withTrashed();
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class);
    }

    public function isGeneral(): bool
    {
        return $this->order_type === OrderType::General;
    }

    public function isSpecific(): bool
    {
        return $this->order_type === OrderType::Specific;
    }
}
