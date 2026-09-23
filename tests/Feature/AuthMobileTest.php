<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AuthMobileTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_otp_normalizes_local_mobile_to_e164(): void
    {
        $this->postJson('/api/auth/otp/send', ['mobile' => '0555555555'])
            ->assertOk();

        $this->assertDatabaseHas('otp_codes', [
            'identifier' => '+966555555555',
        ]);
    }

    public function test_send_otp_normalizes_international_mobile_to_e164(): void
    {
        $this->postJson('/api/auth/otp/send', ['mobile' => '+966555555555'])
            ->assertOk();

        $this->assertDatabaseHas('otp_codes', [
            'identifier' => '+966555555555',
        ]);
    }

    public function test_send_otp_normalizes_country_code_without_plus_to_e164(): void
    {
        $this->postJson('/api/auth/otp/send', ['mobile' => '966555555555'])
            ->assertOk();

        $this->assertDatabaseHas('otp_codes', [
            'identifier' => '+966555555555',
        ]);
    }

    public function test_send_otp_rejects_invalid_mobile(): void
    {
        $this->postJson('/api/auth/otp/send', ['mobile' => '0111111111'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mobile');
    }

    public function test_verify_otp_normalizes_mobile_before_lookup(): void
    {
        $mock = Mockery::mock(OtpService::class);
        $mock->shouldReceive('verifyOtp')
            ->once()
            ->with('+966555555555', '1234')
            ->andReturn(true);
        $mock->shouldReceive('findByMobile')
            ->once()
            ->with('+966555555555')
            ->andReturn(null);
        $mock->shouldReceive('createPendingToken')
            ->once()
            ->with('registration', '+966555555555')
            ->andReturn('temp-token');

        $this->app->instance(OtpService::class, $mock);

        $this->postJson('/api/auth/otp/verify', ['mobile' => '0555555555', 'otp' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.is_new', true);
    }

    public function test_verify_otp_logs_in_existing_user_stored_in_e164(): void
    {
        $user = User::factory()->create(['mobile' => '+966555555555']);

        $mock = Mockery::mock(OtpService::class);
        $mock->shouldReceive('verifyOtp')
            ->once()
            ->with('+966555555555', '1234')
            ->andReturn(true);
        $mock->shouldReceive('findByMobile')
            ->once()
            ->with('+966555555555')
            ->andReturn($user);
        $mock->shouldReceive('createToken')
            ->once()
            ->with($user)
            ->andReturn('token-abc');

        $this->app->instance(OtpService::class, $mock);

        $this->postJson('/api/auth/otp/verify', ['mobile' => '0555555555', 'otp' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.is_new', false)
            ->assertJsonPath('data.token', 'token-abc');
    }

    public function test_registration_stores_mobile_in_e164(): void
    {
        $city = City::factory()->create();
        $token = $this->pendingRegistrationToken('+966555555555');

        $this->postJson('/api/auth/register', [
            'temp_token' => $token,
            'full_name'  => 'Test User',
            'city_id'    => $city->id,
        ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'mobile'    => '+966555555555',
            'full_name' => 'Test User',
        ]);
    }

    private function pendingRegistrationToken(string $mobile): string
    {
        return $this->app->make(OtpService::class)->createPendingToken('registration', $mobile);
    }
}
