<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminPasswordRequest;
use App\Services\AdminCredentialService;

class AdminPasswordController extends Controller
{
    public function __construct(private AdminCredentialService $credentials) {}

    public function setup(AdminPasswordRequest $request)
    {
        return $this->success($this->credentials->completeSetup($request->validated('challenge'), $request->validated('password')))
            ->header('Cache-Control', 'no-store, private');
    }

    public function change(AdminPasswordRequest $request)
    {
        $this->credentials->changePassword($request->user(), $request->validated('current_password'), $request->validated('password'));

        return $this->success()->header('Cache-Control', 'no-store, private');
    }
}
