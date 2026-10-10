<?php

namespace Tests\Feature;

use App\Services\ChatRealtimeService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatBroadcastTransactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // This test needs real commits, without RefreshDatabase's outer transaction.
        $this->artisan('migrate:fresh')->assertExitCode(0);
        RefreshDatabaseState::$migrated = false;
    }

    public function test_broadcast_is_queued_only_after_commit_and_never_after_rollback(): void
    {
        config(['chat_realtime.enabled' => true, 'chat_realtime.queue_connection' => 'database']);
        DB::beginTransaction();
        app(ChatRealtimeService::class)->message(1, 1);
        $this->assertDatabaseCount('jobs', 0);
        DB::rollBack();
        $this->assertDatabaseCount('jobs', 0);

        DB::beginTransaction();
        app(ChatRealtimeService::class)->message(1, 2);
        $this->assertDatabaseCount('jobs', 0);
        DB::commit();
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('jobs', ['queue' => 'realtime']);
    }
}
