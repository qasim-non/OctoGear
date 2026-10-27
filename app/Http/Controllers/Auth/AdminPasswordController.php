<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminPasswordRequest;
use App\Http\Responses\AuthSessionResponse;
use App\Services\AdminCredentialService;

class AdminPasswordController extends Controller
{
    public function __construct(private AdminCredentialService $credentials, private AuthSessionResponse $sessions) {}

    public function setup(AdminPasswordRequest $request)
    {
        return $this->sessions->make($request, $this->credentials->completeSetup($request->validated('challenge'), $request->validated('password'), $request->header('X-Auth-Client') === 'web'));
    }

    public function change(AdminPasswordRequest $request)
    {
        $this->credentials->changePassword($request->user(), $request->validated('current_password'), $request->validated('password'));

        return $this->sessions->clear($this->success());
    }
}
