<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreCarComponent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_car_id',
        'component_id',
        'part_number',
        'description',
        'price',
        'stock_quantity',
        'warranty_months',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock_quantity' => 'integer',
            'warranty_months' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function storeCar(): BelongsTo
    {
        return $this->belongsTo(StoresCar::class, 'store_car_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'store_car_component_id');
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }
}
