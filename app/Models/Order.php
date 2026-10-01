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

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_type',
        'quantity',
        'customer_image_disk',
        'customer_image_path',
        'customer_image_mime_type',
        'customer_image_size_bytes',
        'status',
        'offered_price',
        'requested_unit_price',
        'notes',
        'customer_id',
        'store_car_component_id',
        'model_id',
        'accepted_store_id',
        'idempotency_key',
        'idempotency_fingerprint',
    ];

    protected $hidden = [
        'deleted_at',
        'idempotency_key',
        'idempotency_fingerprint',
        'customer_image_disk',
        'customer_image_path',
    ];

    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'status' => OrderStatus::class,
            'quantity' => 'integer',
            'customer_image_size_bytes' => 'integer',
            'offered_price' => 'integer',
            'requested_unit_price' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (Order $order): void {
            app(ImageStorageService::class)->delete($order->customerImage());
        });
    }

    public function customerImage(): array
    {
        return [
            'disk' => $this->customer_image_disk,
            'path' => $this->customer_image_path,
            'mime_type' => $this->customer_image_mime_type,
            'size_bytes' => $this->customer_image_size_bytes,
        ];
    }

    public function customerImageUrl(): ?string
    {
        return filled($this->customer_image_disk) && filled($this->customer_image_path)
            ? route('media.order-image.show', ['order' => $this->id], false)
            : null;
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
     |   Order → model_id (what car the part is for, no store selected yet)
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

    /**
     * The car model this order is for.
     * Used mainly for general orders (no specific component selected yet).
     * For specific orders, the model can be derived from storeCarComponent.
     */
    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'model_id');
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
