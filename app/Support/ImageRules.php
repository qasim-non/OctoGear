<?php

namespace App\Support;

class ImageRules
{
    /**
     * Feature-specific non-null settings override the central defaults.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<int, string>
     */
    public static function file(array $overrides = []): array
    {
        $settings = array_replace(
            config('images'),
            array_filter($overrides, static fn ($value) => $value !== null),
        );

        return [
            'bail',
            'file',
            'image',
            'mimes:'.implode(',', $settings['allowed_extensions']),
            'max:'.$settings['max_file_size_kb'],
            'dimensions:min_width='.$settings['min_width']
                .',min_height='.$settings['min_height']
                .',max_width='.$settings['max_width']
                .',max_height='.$settings['max_height'],
        ];
    }
}
