<?php

namespace Tests\Unit;

use App\Exceptions\BusinessRuleException;
use App\Models\Admin;
use App\Models\City;
use App\Models\User;
use App\Services\AuthService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_otp_throws_rate_limited_when_code_is_invalid(): void
    {
        $otp = Mockery::mock(OtpService::class);
        $otp->shouldReceive('verifyOtp')
            ->once()
            ->with('+966555555555', '0000')
            ->andReturn(false);

        try {
            (new AuthService($otp))->verifyOtp('+966555555555', '0000');

            $this->fail('Expected BusinessRuleException to be thrown.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('auth.otp.rate_limited', $e->messageKey());
            $this->assertSame(['max' => 5], $e->messageParams());
            $this->assertSame(422, $e->statusCode());
        }
    }

    public function test_verify_otp_returns_token_for_existing_user(): void
    {
        $user = User::factory()->make(['id' => 1]);

        $otp = Mockery::mock(OtpService::class);
        $otp->shouldReceive('verifyOtp')->once()->andReturn(true);
        $otp->shouldReceive('findByMobile')->once()->andReturn($user);
        $otp->shouldReceive('createToken')->once()->with($user)->andReturn('token-abc');

        $result = (new AuthService($otp))->verifyOtp('+966555555555', '1234');

        $this->assertSame('token-abc', $result['token']);
        $this->assertFalse($result['is_new']);
        $this->assertSame('customer', $result['type']);
    }

    public function test_verify_otp_returns_temp_token_for_new_mobile(): void
    {
        $otp = Mockery::mock(OtpService::class);
        $otp->shouldReceive('verifyOtp')->once()->andReturn(true);
        $otp->shouldReceive('findByMobile')->once()->andReturn(null);
        $otp->shouldReceive('createPendingToken')
            ->once()
            ->with('registration', '+966555555555')
            ->andReturn('temp-token');

        $result = (new AuthService($otp))->verifyOtp('+966555555555', '1234');

        $this->assertSame('temp-token', $result['temp_token']);
        $this->assertTrue($result['is_new']);
        $this->assertNull($result['type']);
    }

    public function test_register_throws_when_temp_token_is_invalid(): void
    {
        $otp = Mockery::mock(OtpService::class);
        $otp->shouldReceive('consumePendingToken')
            ->once()
            ->with('registration', 'bad-token')
            ->andReturn(null);

        try {
            (new AuthService($otp))->register('bad-token', 'Test User', 1);

            $this->fail('Expected BusinessRuleException to be thrown.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('auth.register.token_invalid', $e->messageKey());
            $this->assertSame(422, $e->statusCode());
        }
    }

    public function test_register_creates_user_and_returns_token(): void
    {
        $city = City::factory()->create();

        $otp = Mockery::mock(OtpService::class);
        $otp->shouldReceive('consumePendingToken')
            ->once()
            ->with('registration', 'valid-token')
            ->andReturn('+966555555555');
        $otp->shouldReceive('createToken')
            ->once()
            ->with(Mockery::type(User::class))
            ->andReturn('new-token');

        $result = (new AuthService($otp))->register('valid-token', 'Test User', $city->id);

        $this->assertSame('new-token', $result['token']);
        $this->assertDatabaseHas('users', [
            'mobile' => '+966555555555',
            'full_name' => 'Test User',
            'type' => 'customer',
            'city_id' => $city->id,
        ]);
    }

    public function test_admin_login_throws_when_credentials_are_invalid(): void
    {
        Admin::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('secret123'),
        ]);

        try {
            (new AuthService(app(OtpService::class)))->adminLogin('admin@test.com', 'wrong-password');

            $this->fail('Expected BusinessRuleException to be thrown.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('auth.login.invalid', $e->messageKey());
            $this->assertSame(401, $e->statusCode());
        }
    }

    public function test_admin_login_throws_when_admin_is_blocked(): void
    {
        Admin::factory()->blocked()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('secret123'),
        ]);

        try {
            (new AuthService(app(OtpService::class)))->adminLogin('admin@test.com', 'secret123');

            $this->fail('Expected BusinessRuleException to be thrown.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('auth.admin.blocked', $e->messageKey());
            $this->assertSame(403, $e->statusCode());
        }
    }

    public function test_admin_login_returns_token_for_active_admin(): void
    {
        Admin::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('secret123'),
        ]);

        $result = (new AuthService(app(OtpService::class)))->adminLogin('admin@test.com', 'secret123');

        $this->assertIsString($result['token']);
        $this->assertNotEmpty($result['token']);
    }
}
