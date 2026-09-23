<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected AdminDashboardService $service,
    ) {}

    public function show()
    {
        return $this->success($this->service->overview());
    }
}
