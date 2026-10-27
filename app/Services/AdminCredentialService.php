<?php

namespace App\Services;

use App\Enums\PreferredLanguage;
use App\Exceptions\BusinessRuleException;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminCredentialService
{
    public function __construct(private AuthSessionService $sessions) {}

    public function login(string $email, string $password, ?PreferredLanguage $language, bool $browser = false): array
    {
        return DB::transaction(function () use ($email, $password, $language, $browser) {
            $admin = Admin::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->lockForUpdate()->first();
            if (! $admin) {
                Hash::make($password);
            }
            if (! $admin || ! Hash::check($password, $admin->password)) {
                Log::warning('Admin login failed', ['email' => $email]);
                throw new BusinessRuleException(messageKey: 'auth.login.invalid', statusCode: 401);
            }
            $this->assertActive($admin);
            if ($language && $admin->preferred_language === null) {
                $admin->preferred_language = $language;
                $admin->save();
            }
            if ($admin->must_change_password) {
                if (! $admin->temporary_password_expires_at || $admin->temporary_password_expires_at->isPast()) {
                    throw new BusinessRuleException(messageKey: 'admin_accounts.temporary_expired', statusCode: 403);
                }
                $challenge = bin2hex(random_bytes(32));
                DB::table('admin_password_challenges')->updateOrInsert(['admin_id' => $admin->getKey()], [
                    'token_hash' => hash('sha256', $challenge), 'expires_at' => now()->addMinutes(10),
                ]);

                return ['requires_password_change' => true, 'challenge' => $challenge, 'expires_in' => 600];
            }

            return $this->session($admin, $browser);
        });
    }

    public function completeSetup(string $challenge, string $password, bool $browser = false): array
    {
        $hash = hash('sha256', $challenge);
        $id = DB::table('admin_password_challenges')->where('token_hash', $hash)->value('admin_id');

        return DB::transaction(function () use ($id, $hash, $password, $browser) {
            $admin = $id ? Admin::whereKey($id)->lockForUpdate()->first() : null;
            $row = $id ? DB::table('admin_password_challenges')->where('admin_id', $id)->lockForUpdate()->first() : null;
            if (! $admin || ! $row || ! hash_equals($row->token_hash, $hash) || now()->gte($row->expires_at)
                || ! $admin->must_change_password || ! $admin->temporary_password_expires_at || $admin->temporary_password_expires_at->isPast()) {
                throw new BusinessRuleException(messageKey: 'admin_accounts.challenge_expired', statusCode: 422);
            }
            $this->assertActive($admin);
            $this->replacePassword($admin, $password);
            $this->audit($admin, 'password_setup', $admin->getKey());

            return $this->session($admin, $browser);
        });
    }

    public function changePassword(Admin $actor, string $current, string $password): void
    {
        DB::transaction(function () use ($actor, $current, $password) {
            $admin = Admin::whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $this->assertActive($admin);
            $this->verifyCurrent($admin, $current);
            $this->replacePassword($admin, $password);
            $this->audit($admin, 'password_changed', $admin->getKey());
        });
    }

    public function verifyCurrent(Admin $admin, string $password): void
    {
        if (! Hash::check($password, $admin->password)) {
            throw new BusinessRuleException(messageKey: 'auth.general.validation_failed', statusCode: 422, errors: ['current_password' => [__('admin_accounts.current_password_invalid')]]);
        }
    }

    public function revoke(Admin $admin): void
    {
        $this->sessions->revokeAll($admin);
        DB::table('admin_password_challenges')->where('admin_id', $admin->getKey())->delete();
    }

    public function audit(Admin $admin, string $action, ?int $actorId, array $details = []): void
    {
        DB::table('admin_security_events')->insert([
            'admin_id' => $admin->getKey(), 'actor_id' => $actorId, 'action' => $action,
            'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    private function replacePassword(Admin $admin, string $password): void
    {
        if (Hash::check($password, $admin->password)) {
            throw new BusinessRuleException(messageKey: 'auth.general.validation_failed', statusCode: 422, errors: ['password' => [__('admin_accounts.password_different')]]);
        }
        $admin->forceFill(['password' => $password, 'must_change_password' => false, 'temporary_password_expires_at' => null,
            'password_changed_at' => now(), 'security_version' => $admin->security_version + 1])->save();
        $this->revoke($admin);
    }

    private function assertActive(Admin $admin): void
    {
        if (! $admin->isActive()) {
            Log::warning('Inactive admin login or password attempt', ['admin_id' => $admin->getKey()]);
            throw new BusinessRuleException(messageKey: 'auth.admin.blocked', statusCode: 403);
        }
    }

    private function session(Admin $admin, bool $browser): array
    {
        $admin->forceFill(['last_login_at' => now()])->save();

        return $this->sessions->start($admin, $browser);
    }
}
