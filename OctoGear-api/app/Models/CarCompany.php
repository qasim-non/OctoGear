<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarCompany extends Model
{
    use SoftDeletes;

    /**
     * Laravel guesses "car_companies" from "CarCompany", but our table is "cars_companies".
     */
    public function getTable(): string
    {
        return 'cars_companies';
    }

    protected $fillable = [
        'name_en',
        'name_ar',
        'country_id',
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

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function carNames(): HasMany
    {
        return $this->hasMany(CarName::class);
    }

    public function storeCompanies(): HasMany
    {
        return $this->hasMany(StoreCompany::class, 'company_id');
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_companies');
    }
}
