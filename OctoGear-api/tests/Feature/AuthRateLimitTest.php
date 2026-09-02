<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_otp_is_limited_per_ip_and_mobile(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->withHeaders(['REMOTE_ADDR' => '1.2.3.4'])
                ->postJson('/api/auth/otp/send', ['mobile' => '0555555555'])
                ->assertOk();
        }

        $this->withHeaders(['REMOTE_ADDR' => '1.2.3.4'])
            ->postJson('/api/auth/otp/send', ['mobile' => '0555555555'])
            ->assertStatus(429);
    }

    public function test_send_otp_limit_is_scoped_per_mobile(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->withHeaders(['REMOTE_ADDR' => '2.2.2.2'])
                ->postJson('/api/auth/otp/send', ['mobile' => '0555555555'])
                ->assertOk();
        }

        // A different mobile from the same IP is still blocked (IP cap),
        // but a different mobile from a different IP proceeds.
        $this->withHeaders(['REMOTE_ADDR' => '2.2.2.2'])
            ->postJson('/api/auth/otp/send', ['mobile' => '0555555556'])
            ->assertStatus(429);

        $this->withHeaders(['REMOTE_ADDR' => '3.3.3.3'])
            ->postJson('/api/auth/otp/send', ['mobile' => '0555555556'])
            ->assertOk();
    }

    public function test_admin_login_is_rate_limited(): void
    {
        Admin::factory()->create(['email' => 'admin@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/admin/login', [
                'email'    => 'admin@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/auth/admin/login', [
            'email'    => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_global_api_limiter_applies_thirty_per_minute(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->withHeaders(['REMOTE_ADDR' => '9.9.9.9'])
                ->getJson('/api/reference/cities')
                ->assertOk();
        }

        $this->withHeaders(['REMOTE_ADDR' => '9.9.9.9'])
            ->getJson('/api/reference/cities')
            ->assertStatus(429);
    }
}