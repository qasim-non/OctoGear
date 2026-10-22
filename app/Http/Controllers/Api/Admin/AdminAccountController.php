<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAccountActionRequest;
use App\Http\Requests\Admin\AdminAccountIndexRequest;
use App\Http\Requests\Admin\AdminAccountSaveRequest;
use App\Http\Resources\AdminAccountResource;
use App\Models\Admin;
use App\Repositories\AdminAccountRepository;
use App\Services\AdminAccountService;
use App\Support\AdminPermissions;

class AdminAccountController extends Controller
{
    public function __construct(private AdminAccountService $service, private AdminAccountRepository $accounts) {}

    public function index(AdminAccountIndexRequest $request)
    {
        return $this->paginated($this->accounts->index($request->validated())->through(fn ($admin) => new AdminAccountResource($admin)))
            ->header('Cache-Control', 'no-store, private');
    }

    public function roles()
    {
        return $this->success(array_map(fn (AdminRole $role) => ['value' => $role->value, 'permissions' => AdminPermissions::forRole($role)], AdminRole::cases()))
            ->header('Cache-Control', 'no-store, private');
    }

    public function events(AdminAccountIndexRequest $request, Admin $admin)
    {
        return $this->paginated($this->accounts->events($admin))->header('Cache-Control', 'no-store, private');
    }

    public function store(AdminAccountSaveRequest $request)
    {
        return $this->created(new AdminAccountResource($this->service->create($request->user(), $request->validated())))
            ->header('Cache-Control', 'no-store, private');
    }

    public function update(AdminAccountSaveRequest $request, Admin $admin)
    {
        return $this->success(new AdminAccountResource($this->service->update($request->user(), $admin, $request->validated(), 'updated')))
            ->header('Cache-Control', 'no-store, private');
    }

    public function resetPassword(AdminAccountActionRequest $request, Admin $admin)
    {
        return $this->success(new AdminAccountResource($this->service->update($request->user(), $admin, $request->validated(), 'password_reset')))
            ->header('Cache-Control', 'no-store, private');
    }

    public function destroy(AdminAccountActionRequest $request, Admin $admin)
    {
        $this->service->update($request->user(), $admin, $request->validated(), 'archived');

        return $this->success()->header('Cache-Control', 'no-store, private');
    }
}
