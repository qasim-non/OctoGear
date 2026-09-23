<?php

namespace App\Models;

use App\Enums\SectionCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreCarSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_car_id',
        'section_id',
        'condition',
    ];

    protected function casts(): array
    {
        return [
            'condition' => SectionCondition::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function storeCar(): BelongsTo
    {
        return $this->belongsTo(StoresCar::class, 'store_car_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CarSection::class, 'section_id');
    }
}
