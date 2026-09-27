<?php

namespace App\Http\Requests\Customer;

use App\Support\ImageRules;

trait CustomerCarPictureRules
{
    /**
     * Shared validation messages for multipart customer-car photos.
     *
     * @return array<string, string>
     */
    public static function pictureMessages(bool $picturesRequired = false): array
    {
        return [
            ...($picturesRequired ? [
                'pictures.required' => __('auth.validation.pictures.required'),
                'pictures.min' => __('auth.validation.pictures.min'),
            ] : []),
            'pictures.array' => __('auth.validation.pictures.array'),
            'pictures.max' => __('auth.validation.pictures.max'),
            'pictures.*.file' => __('auth.validation.pictures.file'),
            'pictures.*.image' => __('auth.validation.pictures.image'),
            'pictures.*.mimes' => __('auth.validation.pictures.mimes'),
            'pictures.*.max' => __('auth.validation.pictures.file_max'),
            'pictures.*.dimensions' => __('auth.validation.pictures.dimensions'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function pictureFileRules(): array
    {
        return ImageRules::file(config('customer_car_media'));
    }

    public static function pictureLimit(): int
    {
        return (int) (config('customer_car_media.max_files') ?? config('images.max_files'));
    }
}
