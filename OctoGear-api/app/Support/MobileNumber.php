<?php

namespace App\Support;

class MobileNumber
{
    /**
     * Normalize a Saudi mobile number to E.164 form (+9665XXXXXXXX).
     *
     * Accepts the local (05XXXXXXXX), national (5XXXXXXXX), national with
     * country code (9665XXXXXXXX) and international (+9665XXXXXXXX)
     * formats, with any common separators. Returns null when the value is
     * not a valid Saudi mobile number.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if (preg_match('/^9665\d{8}$/', $digits)) {
            return '+'.$digits;
        }

        if (preg_match('/^5\d{8}$/', $digits)) {
            return '+966'.$digits;
        }

        if (preg_match('/^05\d{8}$/', $digits)) {
            return '+966'.substr($digits, 1);
        }

        return null;
    }
}