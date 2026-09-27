<?php

namespace App\Models\Concerns;

trait HasRegistrationImage
{
    public function registrationImage(): array
    {
        return [
            'disk' => $this->commercial_registration_disk,
            'path' => $this->commercial_registration_path,
            'mime_type' => $this->commercial_registration_mime_type,
            'size_bytes' => $this->commercial_registration_size_bytes,
        ];
    }

    public function hasRegistrationImage(): bool
    {
        return filled($this->commercial_registration_disk) && filled($this->commercial_registration_path);
    }
}
