<?php

namespace App\Events;

use App\Models\StoreRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoreRequestReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(public StoreRequest $request) {}
}
