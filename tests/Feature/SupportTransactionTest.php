<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SupportChatService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportTransactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertExitCode(0);
        RefreshDatabaseState::$migrated = false;
    }

    public function test_first_message_and_conversation_rollback_together_and_queue_only_after_commit(): void
    {
        config(['chat_realtime.enabled' => true, 'chat_realtime.queue_connection' => 'database']);
        $user = User::factory()->create();
        $input = ['content' => 'Help', 'client_message_id' => (string) Str::uuid()];
        DB::beginTransaction();
        app(SupportChatService::class)->send($user, $input);
        $this->assertDatabaseCount('support_conversations', 1);
        $this->assertDatabaseCount('support_messages', 1);
        $this->assertDatabaseCount('jobs', 0);
        DB::rollBack();
        $this->assertDatabaseCount('support_conversations', 0);
        $this->assertDatabaseCount('support_messages', 0);
        $this->assertDatabaseCount('jobs', 0);

        DB::beginTransaction();
        app(SupportChatService::class)->send($user, $input);
        $this->assertDatabaseCount('jobs', 0);
        DB::commit();
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('jobs', ['queue' => 'realtime']);
    }
}
