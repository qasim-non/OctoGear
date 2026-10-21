<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 10)->default('open');
            $table->unsignedBigInteger('version')->default(1);
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->unsignedBigInteger('user_read_through_id')->default(0);
            $table->unsignedBigInteger('support_read_through_id')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->foreign('closed_by')->references('employee_id')->on('admin')->restrictOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'last_activity_at', 'id']);
        });
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_conversation_id')->constrained()->restrictOnDelete();
            $table->string('sender_kind', 10);
            $table->unsignedBigInteger('sender_id');
            $table->uuid('client_message_id');
            $table->text('content');
            $table->timestamps();
            $table->unique(['sender_kind', 'sender_id', 'client_message_id'], 'support_message_retry_unique');
            $table->index(['support_conversation_id', 'id']);
        });
        Schema::create('support_conversation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_conversation_id')->constrained()->restrictOnDelete();
            $table->string('actor_kind', 10);
            $table->unsignedBigInteger('actor_id');
            $table->string('from_status', 10);
            $table->string('to_status', 10);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_conversation_events');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
    }
};
