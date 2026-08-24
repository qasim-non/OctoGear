<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CmsResource;
use App\Models\Cms;
use Illuminate\Support\Facades\Cache;

class CmsController extends Controller
{
    public function show(Cms $cms)
    {
        return $this->success(new CmsResource($cms));
    }
}
