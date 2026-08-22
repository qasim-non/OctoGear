<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * $fillable = Mass Assignment Protection.
     *
     * Only these fields can be set via Country::create([...]) or $country->fill([...]).
     * If someone tries to mass-assign a field NOT in this array, it's silently ignored.
     *
     * Why these fields?
     * - name_en: The English name (e.g., "Saudi Arabia") — set when admin creates a country
     * - name_ar: The Arabic name (e.g., "المملكة العربية السعودية") — set when admin creates a country
     * - No other fields should be user-input — 'id', 'created_at', 'updated_at', 'deleted_at'
     *   are managed by Laravel automatically
     */
    protected $fillable = [
        'name_en',
        'name_ar',
    ];

    /**
     * $hidden = JSON Output Protection.
     *
     * When this model is converted to JSON (via API response), these fields are REMOVED.
     *
     * Why hide deleted_at?
     * - It's an internal soft-delete timestamp
     * - The API consumer doesn't need to know when/if a country was soft-deleted
     * - Showing it could leak implementation details
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * casts() = Type Conversion.
     *
     * Converts raw database values to proper PHP types.
     *
     * - created_at/updated_at/deleted_at → 'datetime': Instead of raw strings like
     *   "2024-01-15 10:30:00", you get Carbon instances with methods like:
     *   $country->created_at->diffForHumans()  → "2 hours ago"
     *   $country->created_at->isPast()          → true
     *
     * We don't need enum casts here because Country has no enum columns.
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * RELATIONSHIPS
     *
     * Each method defines how this model connects to other models.
     *
     * belongsTo = "This model BELONGS TO another model" (has a foreign key)
     * hasMany = "This model HAS MANY of another model" (other model has FK to us)
     *
     * Example: A Country has many Cities.
     *          Saudi Arabia → [Riyadh, Jeddah, Dammam]
     */

    /**
     * A country has many cities.
     *
     * City table has: country_id → countries.id
     * So: Country::find(1)->cities → returns all cities in that country
     *
     * @return HasMany
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /**
     * A country has many car companies.
     *
     * cars_companies table has: country_id → countries.id
     * Example: Japan → [Toyota, Nissan, Honda]
     *
     * @return HasMany
     */
    public function carCompanies(): HasMany
    {
        return $this->hasMany(CarCompany::class);
    }
}
