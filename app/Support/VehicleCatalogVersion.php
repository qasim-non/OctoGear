<?php

namespace App\Support;

use App\Models\CarCompany;
use App\Models\CarName;

final class VehicleCatalogVersion
{
    public static function for(CarCompany|CarName $record): string
    {
        return hash('sha256', json_encode([
            $record->id, $record->name_en, $record->name_ar,
            $record instanceof CarCompany ? $record->country_id : $record->car_company_id,
        ], JSON_THROW_ON_ERROR));
    }
}
