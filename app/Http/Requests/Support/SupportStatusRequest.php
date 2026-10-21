<?php

namespace App\Http\Requests\Support;

use App\Models\Admin;

class SupportStatusRequest extends SupportRequest
{
    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:1'],
            'status' => $this->user() instanceof Admin ? ['required', 'in:open,closed'] : ['prohibited']];
    }
}
