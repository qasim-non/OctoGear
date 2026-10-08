<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyDeviceTokenMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_preserves_users_sessions_and_push_registrations(): void
    {
        $user = User::factory()->customer()->create();
        $session = $user->createToken('customer-app')->accessToken;
        $device = $user->deviceTokens()->create([
            'personal_access_token_id' => $session->id,
            'token' => 'active-fcm-token',
            'platform' => 'android',
            'locale' => 'en',
            'last_seen_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_10_08_000003_drop_device_token_from_users_table.php');
        $deviceBefore = $device->fresh()->getRawOriginal();

        $migration->down();
        $this->assertTrue(Schema::hasColumn('users', 'device_token'));
        $this->assertNull(DB::table('users')->where('id', $user->id)->value('device_token'));
        DB::table('users')->where('id', $user->id)->update(['device_token' => 'stale-legacy-token']);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('users', 'device_token'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'mobile' => $user->mobile]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $session->id, 'token' => $session->token]);
        $this->assertSame($deviceBefore, $device->fresh()->getRawOriginal());
    }
}
