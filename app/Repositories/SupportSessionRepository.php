<?php

namespace App\Repositories;

use App\Enums\AdminStatus;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\User;

class SupportSessionRepository
{
    public function __construct(private ActiveSessionChannelRepository $sessions) {}

    public function userChannels(int $id): array
    {
        $ids = User::whereKey($id)->where('status', UserStatus::Unblocked)->pluck('id');

        return $this->sessions->channels(new User, $ids->all(), 'private-chat.sessions.');
    }

    public function adminChannels(): array
    {
        $ids = Admin::where('status', AdminStatus::Active)->get(['employee_id', 'assigned_role', 'status', 'must_change_password'])
            ->filter(fn (Admin $admin) => $admin->hasPermission('support.access'))->modelKeys();

        return $this->sessions->channels(new Admin, $ids, 'private-support.admin.sessions.');
    }
}
