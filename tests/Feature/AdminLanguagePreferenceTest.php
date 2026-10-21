<?php

namespace Tests\Feature;

use App\Enums\PreferredLanguage;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminLanguagePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_login_records_language_and_later_login_preserves_saved_choice(): void
    {
        $admin = Admin::factory()->create();
        foreach (['ar', 'en'] as $language) {
            $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'password', 'preferred_language' => $language])->assertOk();
            $this->assertSame(PreferredLanguage::Arabic, $admin->fresh()->preferred_language);
        }
        $token = $admin->createToken('browser', ['admin'])->plainTextToken;
        $this->withToken($token)->getJson('/api/admin/profile')->assertOk()->assertJsonPath('data.preferred_language', 'ar');
    }

    public function test_language_patch_is_self_scoped_repeatable_and_does_not_change_other_profile_fields(): void
    {
        $admin = Admin::factory()->create();
        $other = Admin::factory()->create(['preferred_language' => 'ar']);
        $before = $admin->getAttributes();
        $token = $admin->createToken('browser', ['admin'])->plainTextToken;
        foreach (['ar', 'en', 'en'] as $language) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->patchJson('/api/admin/profile/language', ['preferred_language' => $language, 'employee_id' => $other->getKey(), 'assigned_role' => 'developer'])
                ->assertOk()->assertJsonPath('data.preferred_language', $language)->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->assertSame(PreferredLanguage::English, $admin->fresh()->preferred_language);
        $this->assertSame(PreferredLanguage::Arabic, $other->fresh()->preferred_language);
        foreach (['name', 'email', 'password', 'assigned_role', 'status'] as $field) {
            $this->assertSame($before[$field], $admin->fresh()->getRawOriginal($field));
        }
    }

    public function test_reads_never_write_and_old_login_clients_remain_compatible(): void
    {
        $admin = Admin::factory()->create();
        $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();
        $this->actingAs($admin, 'admin')->getJson('/api/admin/profile', ['Accept-Language' => 'ar'])->assertOk()->assertJsonPath('data.preferred_language', null);
        $this->assertNull($admin->fresh()->preferred_language);
    }

    public function test_invalid_and_unauthorized_changes_leave_preferences_unchanged(): void
    {
        $admin = Admin::factory()->create(['preferred_language' => 'en']);
        $this->patchJson('/api/admin/profile/language', ['preferred_language' => 'ar'])->assertUnauthorized();
        $this->actingAs($admin, 'admin');
        foreach (['en', 'ar'] as $locale) {
            foreach ([null, 'fr', ['ar'], 1] as $value) {
                $this->patchJson('/api/admin/profile/language', ['preferred_language' => $value], ['Accept-Language' => $locale])
                    ->assertUnprocessable()->assertJsonValidationErrors('preferred_language');
            }
        }
        foreach ([User::factory()->customer()->create(), User::factory()->provider()->create(), Admin::factory()->blocked()->create()] as $actor) {
            $this->app['auth']->forgetGuards();
            $this->actingAs($actor, 'sanctum')->patchJson('/api/admin/profile/language', ['preferred_language' => 'ar'])->assertForbidden();
        }
        $this->assertSame(PreferredLanguage::English, $admin->fresh()->preferred_language);
    }

    public function test_failed_login_cannot_set_language_and_invalid_language_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'wrong-password', 'preferred_language' => 'ar'])->assertUnauthorized();
        $this->postJson('/api/auth/admin/login', ['email' => $admin->email, 'password' => 'password', 'preferred_language' => 'fr'])->assertUnprocessable();
        $this->assertNull($admin->fresh()->preferred_language);
    }

    public function test_additive_migration_preserves_existing_admins(): void
    {
        $admin = Admin::factory()->create();
        $migration = require database_path('migrations/2026_10_21_130000_add_preferred_language_to_admin_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('admin', 'preferred_language'));
        $this->assertDatabaseHas('admin', ['employee_id' => $admin->getKey(), 'email' => $admin->email]);
        $migration->up();
        $this->assertNull($admin->fresh()->preferred_language);
    }
}
