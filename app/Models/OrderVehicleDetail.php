<?php

namespace App\Models;

use App\Enums\TransmissionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Submission-time vehicle details; catalog and garage edits do not rewrite them. */
class OrderVehicleDetail extends Model
{
    protected $fillable = [
        'customer_car_id', 'car_name_id', 'car_company_id', 'car_name_en', 'car_name_ar',
        'company_name_en', 'company_name_ar', 'manufacturing_year', 'transmission_type',
        'color_id', 'fuel_type', 'color_name_en', 'color_name_ar', 'fuel_type_en', 'fuel_type_ar',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_year' => 'integer',
            'transmission_type' => TransmissionType::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
