<?php

namespace App\Services;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminAccountService
{
    public function __construct(private AdminCredentialService $credentials) {}

    public function create(Admin $actor, array $data): Admin
    {
        return DB::transaction(function () use ($actor, $data) {
            $actor = $this->lockActor($actor, $data['current_password']);
            $this->uniqueEmail($data['email']);
            $admin = new Admin;
            $admin->fill(collect($data)->only(['name', 'email', 'mobile', 'assigned_role', 'preferred_language'])->all());
            $admin->status = AdminStatus::Active;
            $admin->forceFill(['password' => $data['password'], 'must_change_password' => true,
                'temporary_password_expires_at' => now()->addDay(), 'security_version' => 1])->save();
            $this->credentials->audit($admin, 'created', $actor->getKey(), ['role' => $admin->assigned_role->value]);

            return $admin;
        });
    }

    public function update(Admin $actor, Admin $target, array $data, string $action): Admin
    {
        return DB::transaction(function () use ($actor, $target, $data, $action) {
            $actor = $this->lockActor($actor, $data['current_password']);
            $admin = Admin::whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            if ($admin->security_version !== $data['version']) {
                throw new BusinessRuleException(messageKey: 'admin_accounts.stale', statusCode: 409);
            }
            if ($actor->is($admin)) {
                throw new BusinessRuleException(messageKey: 'admin_accounts.self_change', statusCode: 422);
            }
            $before = ['role' => $admin->assigned_role->value, 'status' => $admin->status->value];
            if ($action === 'updated') {
                $this->uniqueEmail($data['email'], $admin->getKey());
                $admin->fill(collect($data)->only(['name', 'email', 'mobile', 'assigned_role', 'preferred_language', 'status'])->all());
            } elseif ($action === 'password_reset') {
                $admin->forceFill(['password' => $data['password'], 'must_change_password' => true,
                    'temporary_password_expires_at' => now()->addDay()]);
            }
            if ($before['role'] === AdminRole::Admin->value && $before['status'] === AdminStatus::Active->value
                && ($action === 'archived' || $action === 'password_reset' || $admin->assigned_role !== AdminRole::Admin || ! $admin->isActive())
                && ! Admin::where('assigned_role', AdminRole::Admin)->where('status', AdminStatus::Active)
                    ->where('must_change_password', false)->whereKeyNot($admin->getKey())->exists()) {
                throw new BusinessRuleException(messageKey: 'admin_accounts.last_admin', statusCode: 422);
            }
            $admin->security_version++;
            $admin->save();
            $this->credentials->revoke($admin);
            if ($action === 'archived') {
                $admin->delete();
            }
            $this->credentials->audit($admin, $action, $actor->getKey(), [
                'before' => $before, 'after' => ['role' => $admin->assigned_role->value, 'status' => $admin->status->value],
            ]);

            return $admin;
        });
    }

    private function lockActor(Admin $actor, string $password): Admin
    {
        // Serialize access changes, including the last usable administrator check.
        DB::table('admin_security_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
        $fresh = Admin::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
        Gate::forUser($fresh)->authorize('manage', Admin::class);
        $this->credentials->verifyCurrent($fresh, $password);

        return $fresh;
    }

    private function uniqueEmail(string $email, ?int $except = null): void
    {
        if (Admin::withTrashed()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except))->exists()) {
            throw new BusinessRuleException(messageKey: 'auth.general.validation_failed', statusCode: 422, errors: ['email' => [__('admin_accounts.email_taken')]]);
        }
    }
}
