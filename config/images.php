<?php

return [
    // All new images share private storage; each row remembers its disk so
    // future storage changes do not break previously uploaded images.
    'disk' => env('IMAGE_DISK', 'images_local'),

    'max_files' => (int) env('IMAGE_MAX_FILES', 5),
    'max_file_size_kb' => (int) env('IMAGE_MAX_FILE_SIZE_KB', 5 * 1024),
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    'min_width' => 1,
    'min_height' => 1,
    'max_width' => (int) env('IMAGE_MAX_WIDTH', 4096),
    'max_height' => (int) env('IMAGE_MAX_HEIGHT', 4096),
];
