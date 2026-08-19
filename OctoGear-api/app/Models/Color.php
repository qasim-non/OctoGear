<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Color extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_ar',
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
        return $this->hasMany(StoresCar::class);
    }

    public function customerCars(): HasMany
    {
        return $this->hasMany(CustomerCar::class);
    }
}
