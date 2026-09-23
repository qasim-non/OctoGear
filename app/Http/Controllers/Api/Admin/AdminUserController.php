<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Requests\Admin\UserStatusRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\AdminUserService;

class AdminUserController extends Controller
{
    public function __construct(
        protected AdminUserService $service,
    ) {}

    public function index(UserIndexRequest $request)
    {
        $paginator = $this->service->index($request->validated());

        return $this->paginated(
            $paginator->through(fn (User $user) => new AdminUserResource($user)),
        );
    }

    public function customers(UserIndexRequest $request)
    {
        return $this->usersByType($request, 'customer');
    }

    public function providers(UserIndexRequest $request)
    {
        return $this->usersByType($request, 'service provider');
    }

    private function usersByType(UserIndexRequest $request, string $type)
    {
        $filters = $request->validated();
        $filters['type'] = $type;

        $paginator = $this->service->index($filters);

        return $this->paginated(
            $paginator->through(fn (User $user) => new AdminUserResource($user)),
        );
    }

    public function show(User $user)
    {
        return $this->success(new AdminUserResource($this->service->show($user)));
    }

    public function status(UserStatusRequest $request, User $user)
    {
        $status = UserStatus::from($request->validated('status'));
        $user = $this->service->setStatus($user, $status);
        $user = $this->service->show($user);

        $message = $status === UserStatus::Blocked
            ? __('auth.admin.users.blocked')
            : __('auth.admin.users.unblocked');

        return $this->success(new AdminUserResource($user), $message);
    }
}
