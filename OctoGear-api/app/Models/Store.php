<?php

namespace App\Models;

use App\Enums\StoreStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

    /**
     * Why these fields are fillable:
     *
     * - name: Real store name (e.g., "AlFaris Car Maintenance") — provider inputs this
     * - mobile: Store phone number — provider inputs this
     * - nick_name: Short name / alias (e.g., "AlFaris") — provider inputs this
     * - employee_name: Name of employee managing the store — provider inputs this
     * - url_location: Google Maps URL — provider inputs this
     * - commercial_registration_number: Official registration number — provider inputs this
     * - commercial_registration_picture: Path to uploaded image — service sets this after upload
     * - city_id: Which city the store is in — provider selects this
     * - user_id: Which user owns this store — SET IN CODE (not by user input!)
     *
     * Wait — user_id is in $fillable BUT we should NOT let users set it directly.
     * Instead, we use it like this:
     *   $store = $request->user()->stores()->create($data);
     * Laravel auto-sets user_id from the relationship.
     * Having it in $fillable is fine because we control HOW it's set in the controller.
     */
    protected $fillable = [
        'name',
        'mobile',
        'nick_name',
        'employee_name',
        'url_location',
        'status',
        'commercial_registration_number',
        'commercial_registration_picture',
        'city_id',
        'user_id',
    ];

    /**
     * Why hide status and deleted_at:
     *
     * - status: Internal admin status (active/inactive). Users see stores only if active.
     *          If we show inactive stores, it's confusing. Filter in queries instead.
     * - deleted_at: Same as before — internal soft-delete timestamp.
     */
    protected $hidden = [
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,    // "active" → StoreStatus::Active
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
     | Store is the HUB of the marketplace. Everything connects through it:
     |
     |   users ──1:N── stores ──1:N── stores_cars ──1:N── store_car_components
     |                  stores ──1:N── store_pictures
     |                  stores ──M:N── cars_companies (via store_companies)
     |                  stores ──1:N── order_offers
     |                  stores ──1:N── ratings
     */

    /**
     * A store belongs to one user (the owner/provider).
     *
     * $store->owner returns the User model of the store owner.
     * Using 'user' as the method name would conflict with Laravel's built-in auth.
     * So we name it 'owner' to be explicit.
     *
     * @return BelongsTo
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * A store is located in one city.
     *
     * @return BelongsTo
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * A store has many pictures (gallery photos).
     *
     * @return HasMany
     */
    public function pictures(): HasMany
    {
        return $this->hasMany(StorePicture::class);
    }

    /**
     * A store services many car companies (brands).
     *
     * This is a MANY-TO-MANY relationship via the pivot table 'store_companies'.
     * A store can service Toyota, Nissan, BMW, etc.
     * A company (Toyota) can be serviced by many stores.
     *
     * $store->companies → Collection of CarCompany models
     *
     * @return BelongsToMany
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(CarCompany::class, 'store_companies');
    }

    /**
     * A store has many cars in its inventory.
     *
     * $store->cars → Collection of StoresCar models
     *
     * @return HasMany
     */
    public function cars(): HasMany
    {
        return $this->hasMany(StoresCar::class);
    }

    /**
     * A store has submitted many offers on orders.
     *
     * @return HasMany
     */
    public function orderOffers(): HasMany
    {
        return $this->hasMany(OrderOffer::class);
    }

    /**
     * A store has received many ratings from customers.
     *
     * @return HasMany
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * A store has received many orders (via its cars/components).
     *
     * This is an INDIRECT relationship:
     * Store → StoresCar → Order
     * But we can define it directly for convenience.
     *
     * @return HasMany
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the average rating for this store.
     *
     * This is NOT a relationship — it's a helper method.
     * We use it in API Resources: $store->average_rating
     *
     * @return float|null
     */
    public function getAverageRatingAttribute(): ?float
    {
        return $this->ratings()->avg('rating');
    }
}
