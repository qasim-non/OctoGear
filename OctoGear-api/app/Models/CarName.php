<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarName extends Model
{
    use SoftDeletes;

    /**
     * Laravel guesses "car_names" from "CarName", but our table is "cars_names".
     */
    public function getTable(): string
    {
        return 'cars_names';
    }

    protected $fillable = [
        'name_en',
        'name_ar',
        'car_company_id',
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

    public function carCompany(): BelongsTo
    {
        return $this->belongsTo(CarCompany::class);
    }

    public function models(): HasMany
    {
        return $this->hasMany(CarModel::class, 'car_name_id');
    }

    public function storesCars(): HasMany
    {
        return $this->hasMany(StoresCar::class);
    }

    public function customerCars(): HasMany
    {
        return $this->hasMany(CustomerCar::class);
    }
}
