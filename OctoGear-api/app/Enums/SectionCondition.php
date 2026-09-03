<?php

namespace App\Enums;

/**
 * Defines the condition of a car section (part area) on a provider's stocked car.
 *
 * Used in: store_car_sections.condition
 *
 * - Okay: The section is in good/damaged-free working condition
 * - Damaged: The section has damage (can't be sold as-is)
 */
enum SectionCondition: string
{
    case Okay = 'okay';
    case Damaged = 'damaged';
}
