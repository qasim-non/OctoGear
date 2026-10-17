<?php

namespace App\Http\Requests\Shared;

use App\Http\Requests\BaseRequest;
use App\Services\NotificationReadService;

class ReadNotificationPageRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.NotificationReadService::PAGE_SIZE],
            'ids.*' => ['required', 'uuid', 'distinct:ignore_case'],
        ];
    }
}
