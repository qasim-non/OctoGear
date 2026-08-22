<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    ];

    protected $hidden = [
        'deleted_at',
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
        return $this->hasMany(CustomerCarPicture::class, 'car_id');
    }
}
