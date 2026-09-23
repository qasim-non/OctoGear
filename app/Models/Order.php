<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
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
        'customer_image',
        'status',
        'offered_price',
        'notes',
        'customer_id',
        'store_car_component_id',
        'model_id',
        'accepted_store_id',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'status' => OrderStatus::class,
            'quantity' => 'integer',
            'offered_price' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
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
     *
     * @return BelongsTo
     */
    public function storeCarComponent(): BelongsTo
    {
        return $this->belongsTo(StoreCarComponent::class, 'store_car_component_id');
    }

    /**
     * The car model this order is for.
     * Used mainly for general orders (no specific component selected yet).
     * For specific orders, the model can be derived from storeCarComponent.
     *
     * @return BelongsTo
     */
    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'model_id');
    }

    /**
     * Convenience: get the store that owns this order's component.
     * Chain: order → storeCarComponent → storeCar → store
     * NULL for general orders.
     *
     * @return Store|null
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
