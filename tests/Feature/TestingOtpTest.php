<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TestingOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_opt_in_returns_the_logged_code_and_normal_verification_consumes_it(): void
    {
        $this->app->instance('env', 'local');
        config(['otp.expose_for_testing' => true]);
        Log::spy();
        $response = $this->postJson('/api/auth/otp/send', ['mobile' => '500000001'])->assertOk();
        $code = $response->json('data.test_otp');
        $this->assertIsString($code);
        $this->assertMatchesRegularExpression('/^[0-9]{4}$/', $code);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $record = OtpCode::where('identifier', '+966500000001')->firstOrFail();
        $this->assertTrue(Hash::check($code, $record->hashed_otp));
        Log::shouldHaveReceived('info')->with("OTP for +966500000001: {$code}")->once();
        $this->postJson('/api/auth/otp/verify', ['mobile' => '500000001', 'otp' => $code])
            ->assertOk()->assertJsonPath('data.is_new', true);
        $this->assertSame(0, OtpCode::count());
        $this->postJson('/api/auth/otp/verify', ['mobile' => '500000001', 'otp' => $code])->assertUnprocessable();
    }

    public function test_other_environments_never_expose_otp_even_with_flag_enabled(): void
    {
        config(['otp.expose_for_testing' => true]);
        foreach (['production', 'testing'] as $environment) {
            $this->app->instance('env', $environment);
            $this->postJson('/api/auth/otp/send', ['mobile' => '500000001'])->assertOk()
                ->assertJsonMissingPath('data.test_otp')->assertJsonPath('data', null);
        }
    }

    public function test_local_opt_out_preserves_the_original_response(): void
    {
        $this->app->instance('env', 'local');
        config(['otp.expose_for_testing' => false]);
        Log::spy();
        $this->postJson('/api/auth/otp/send', ['mobile' => '500000001'])->assertOk()
            ->assertJsonPath('data', null);
        Log::shouldHaveReceived('info')->once();
    }

    public function test_staging_opt_in_returns_a_working_code_and_resend_replaces_it(): void
    {
        $this->app->instance('env', 'staging');
        config(['otp.expose_for_testing' => true]);
        Log::spy();
        $this->postJson('/api/auth/otp/send', ['mobile' => '500000001'])->assertOk();
        $response = $this->postJson('/api/auth/otp/send', ['mobile' => '500000001'])->assertOk();
        $code = $response->json('data.test_otp');
        $this->assertMatchesRegularExpression('/^[0-9]{4}$/', $code);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(1, OtpCode::count());
        $this->assertTrue(Hash::check($code, OtpCode::firstOrFail()->hashed_otp));
        $this->postJson('/api/auth/otp/verify', ['mobile' => '500000001', 'otp' => $code])
            ->assertOk()->assertJsonPath('data.is_new', true);
        $this->assertSame(0, OtpCode::count());
        Log::shouldNotHaveReceived('info');
    }

    public function test_staging_opt_out_does_not_expose_a_code(): void
    {
        $this->app->instance('env', 'staging');
        config(['otp.expose_for_testing' => false]);
        $this->postJson('/api/auth/otp/send', ['mobile' => '500000001'])->assertOk()
            ->assertJsonMissingPath('data.test_otp')->assertJsonPath('data', null);
    }

    public function test_resend_returns_the_current_stored_code_and_expiry_still_applies(): void
    {
        $this->app->instance('env', 'local');
        config(['otp.expose_for_testing' => true]);
        Log::spy();
        $this->postJson('/api/auth/otp/send', ['mobile' => '500000001'])->assertOk();
        $code = $this->postJson('/api/auth/otp/send', ['mobile' => '500000001'])->assertOk()->json('data.test_otp');
        $this->assertSame(1, OtpCode::count());
        $this->assertTrue(Hash::check($code, OtpCode::firstOrFail()->hashed_otp));
        $this->travel(6)->minutes();
        $this->postJson('/api/auth/otp/verify', ['mobile' => '500000001', 'otp' => $code])->assertUnprocessable();
    }
}
