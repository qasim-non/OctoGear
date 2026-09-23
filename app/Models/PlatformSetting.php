<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    /**
     * No soft deletes — settings should never be "soft deleted".
     * They're either active or removed entirely.
     */

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Static helper to get a setting value by key.
     * Usage: PlatformSetting::get('platform_fee_percentage')
     *
     * @return string|null
     */
    public static function get(string $key): ?string
    {
        $setting = static::where('key', $key)->first();
        return $setting?->value;
    }

    /**
     * Static helper to set a setting value.
     * Usage: PlatformSetting::set('platform_fee_percentage', '5')
     */
    public static function set(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
