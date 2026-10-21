<?php

namespace Tests\Feature;

use App\Enums\PreferredLanguage;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\City;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferredLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_and_providers_can_save_only_their_own_language(): void
    {
        $other = User::factory()->create(['preferred_language' => 'en']);
        foreach ([UserType::Customer, UserType::ServiceProvider] as $type) {
            $user = User::factory()->create(['type' => $type]);
            $token = $user->createToken('test')->plainTextToken;
            $this->app['auth']->forgetGuards();
            foreach (['ar', 'en'] as $language) {
                $this->withToken($token)->patchJson('/api/profile/language', [
                    'preferred_language' => $language, 'user_id' => $other->id,
                    'full_name' => 'Must not change', 'type' => 'admin',
                ])->assertOk()->assertJsonPath('data.preferred_language', $language);
                $this->withToken($token)->getJson('/api/profile')
                    ->assertOk()->assertJsonPath('data.preferred_language', $language);
                $this->assertSame($language, $user->fresh()->preferred_language->value);
                $this->assertSame($user->full_name, $user->fresh()->full_name);
                $this->assertSame($type, $user->fresh()->type);
            }
        }
        $this->assertSame(PreferredLanguage::English, $other->fresh()->preferred_language);
    }

    public function test_missing_or_unsupported_preferences_are_rejected(): void
    {
        $user = User::factory()->create(['preferred_language' => 'ar']);
        $this->actingAs($user, 'sanctum');
        foreach ([[], ['preferred_language' => null], ['preferred_language' => 'fr'],
            ['preferred_language' => 'ar-SA'], ['preferred_language' => ['ar']]] as $payload) {
            $this->patchJson('/api/profile/language', $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('preferred_language');
        }
        $this->assertSame(PreferredLanguage::Arabic, $user->fresh()->preferred_language);
    }

    public function test_guest_and_blocked_users_cannot_change_language(): void
    {
        $this->patchJson('/api/profile/language', ['preferred_language' => 'ar'])->assertUnauthorized();
        $blocked = User::factory()->create(['status' => UserStatus::Blocked]);
        $this->actingAs($blocked, 'sanctum')->patchJson('/api/profile/language', ['preferred_language' => 'ar'])
            ->assertForbidden();
        $this->assertNull($blocked->fresh()->preferred_language);
    }

    public function test_reads_do_not_guess_or_overwrite_preferences_and_repeated_saves_are_stable(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->getJson('/api/profile', ['Accept-Language' => 'ar'])
            ->assertOk()->assertJsonPath('data.preferred_language', null);
        $this->assertNull($user->fresh()->preferred_language);
        $this->patchJson('/api/profile/language', ['preferred_language' => 'en'])->assertOk();
        $savedAt = $user->fresh()->updated_at;
        $this->travel(1)->day();
        $this->patchJson('/api/profile/language', ['preferred_language' => 'en'])->assertOk();
        $this->getJson('/api/profile', ['Accept-Language' => 'ar'])
            ->assertOk()->assertJsonPath('data.preferred_language', 'en');
        $this->assertTrue($savedAt->equalTo($user->fresh()->updated_at));
        $this->travelBack();
    }

    public function test_registration_captures_supported_language_and_explicit_choice_has_priority(): void
    {
        $city = City::factory()->create();
        foreach ([['ar', [], 'ar'], ['en', [], 'en'], ['ar', ['preferred_language' => 'en'], 'en'], ['fr', [], null]] as $index => [$header, $extra, $expected]) {
            $mobile = '+96650000060'.$index;
            $temp = app(OtpService::class)->createPendingToken('registration', $mobile);
            $this->postJson('/api/auth/register', [
                'temp_token' => $temp, 'full_name' => 'Language test', 'city_id' => $city->id, ...$extra,
            ], ['Accept-Language' => $header])->assertOk();
            $this->assertDatabaseHas('users', ['mobile' => $mobile, 'preferred_language' => $expected]);
        }
    }

    public function test_registration_rejects_an_invalid_explicit_language(): void
    {
        $this->postJson('/api/auth/register', [
            'temp_token' => 'unused', 'full_name' => 'Language test',
            'city_id' => City::factory()->create()->id, 'preferred_language' => 'fr',
        ])->assertUnprocessable()->assertJsonValidationErrors('preferred_language');
        $this->assertDatabaseCount('users', 0);
    }
}
