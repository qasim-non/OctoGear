<?php

namespace App\Models;

use App\Enums\SectionCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoresCar extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'manufacturing_year',
        'vehicle_plat_number',
        'car_name_id',
        'color_id',
        'store_id',
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

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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
        return $this->hasMany(StoreCarPicture::class, 'car_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(StoreCarComponent::class, 'store_car_id');
    }

    public function storeCarSections(): HasMany
    {
        return $this->hasMany(StoreCarSection::class, 'store_car_id');
    }

    /**
     * Whether this car reports the given section as in okay condition.
     */
    public function hasSectionOk(int $sectionId): bool
    {
        return $this->storeCarSections
            ->contains(fn (StoreCarSection $s) => $s->section_id === $sectionId && $s->condition === SectionCondition::Okay);
    }
}
