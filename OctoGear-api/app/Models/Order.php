<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_type',
        'quantity',
        'customer_image',
        'status',
        'offered_price',
        'notes',
        'customer_id',
        'store_car_component_id',
        'store_car_id',
        'model_id',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function storeCar(): BelongsTo
    {
        return $this->belongsTo(StoresCar::class, 'store_car_id');
    }

    public function storeCarComponent(): BelongsTo
    {
        return $this->belongsTo(StoreCarComponent::class, 'store_car_component_id');
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'model_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(OrderOffer::class);
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
