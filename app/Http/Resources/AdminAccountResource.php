<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['employee_id' => $this->employee_id, 'name' => $this->name, 'email' => $this->email,
            'mobile' => $this->mobile, 'assigned_role' => $this->assigned_role->value, 'status' => $this->status->value,
            'preferred_language' => $this->preferred_language?->value, 'must_change_password' => $this->must_change_password,
            'temporary_password_expires_at' => $this->temporary_password_expires_at, 'last_login_at' => $this->last_login_at,
            'version' => $this->security_version, 'created_at' => $this->created_at];
    }
}
