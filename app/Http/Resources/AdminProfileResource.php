<?php

namespace App\Http\Resources;

use App\Support\AdminPermissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->employee_id,
            'name' => $this->name,
            'email' => $this->email,
            'assigned_role' => $this->assigned_role->value,
            'status' => $this->status->value,
            'preferred_language' => $this->preferred_language?->value,
            'permissions' => AdminPermissions::forRole($this->assigned_role),
            'password_changed_at' => $this->password_changed_at,
        ];
    }
}
