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
        return $this->sessions->channels(new Admin, Admin::where('status', AdminStatus::Active)->pluck('employee_id')->all(), 'private-support.admin.sessions.');
    }
}
