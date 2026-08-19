<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use SoftDeletes;

    /**
     * $fillable includes 'city_id' because:
     * - When creating a city, you MUST assign it to a country
     * - Example: City::create(['name_en' => 'Riyadh', 'name_ar' => 'الرياض', 'country_id' => 1])
     *
     * The foreign key (country_id) is user-provided data — it's not auto-generated.
     * So it must be in $fillable to be mass-assignable.
     */
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

    /*
     |--------------------------------------------------------------------------
     | RELATIONSHIPS
     |--------------------------------------------------------------------------
     |
     | City sits in the MIDDLE of two relationships:
     |
     |   countries ──1:N── cities ──1:N── users
     |                   cities ──1:N── stores
     |
     | A city BELONGS TO a country (city has country_id)
     | A city HAS MANY users (users have city_id)
     | A city HAS MANY stores (stores have city_id)
     */

    /**
     * A city belongs to one country.
     *
     * City table has: country_id → countries.id
     *
     * $city->country → returns the Country model
     * $city->country->name_en → "Saudi Arabia"
     *
     * The argument to belongsTo() is the RELATED model class.
     * Laravel automatically infers the foreign key: 'country_id'
     *
     * @return BelongsTo
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * A city has many users (both customers and providers live in this city).
     *
     * @return HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * A city has many stores (stores are located in this city).
     *
     * @return HasMany
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
}
