<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FuelType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type_en',
        'type_ar',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function storesCars(): HasMany
    {
        return $this->hasMany(StoresCar::class, 'fuel_type');
    }

    public function customerCars(): HasMany
    {
        return $this->hasMany(CustomerCar::class, 'fuel_type');
    }
}
