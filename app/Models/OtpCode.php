<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OtpCode extends Model
{
    use SoftDeletes;

    /**
     * This model stores OTP verification codes.
     * Users never see this data — it's purely internal.
     *
     * Flow:
     * 1. User requests OTP → we hash the code, store it with expiry
     * 2. User submits OTP → we hash their input, compare with stored hash
     * 3. If match + not expired → verify success
     *
     * We NEVER store the plain OTP — only the hash.
     * Even if someone steals the DB, they can't read the OTPs.
     */
    protected $fillable = [
        'hashed_otp',
        'identifier',
        'expires_at',
    ];

    protected $hidden = [
        'hashed_otp',   // Never expose the hashed OTP
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Check if this OTP has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
