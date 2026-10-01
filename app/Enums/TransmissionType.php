<?php

namespace App\Enums;

enum TransmissionType: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';
    case Unknown = 'unknown';
}
