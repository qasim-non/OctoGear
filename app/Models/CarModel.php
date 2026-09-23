<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarModel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_ar',
        'car_name_id',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    /**
     * We MUST override getTable() because Laravel pluralizes model names
     * to guess the table name. "CarModel" → "car_models" (wrong!)
     * Our table is called "models". So we specify it explicitly.
     */
    public function getTable(): string
    {
        return 'models';
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function carName(): BelongsTo
    {
        return $this->belongsTo(CarName::class, 'car_name_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
