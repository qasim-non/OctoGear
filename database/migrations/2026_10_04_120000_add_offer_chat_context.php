<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('offer_id')->nullable()->unique()->constrained('order_offers')->nullOnDelete();
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->text('content')->change();
            $table->uuid('client_message_id')->nullable();
            $table->unique(['sender_id', 'client_message_id']);
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['sender_id', 'client_message_id']);
            $table->dropIndex(['conversation_id', 'id']);
            $table->dropColumn('client_message_id');
        });
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offer_id');
        });
        // Keep TEXT: shrinking could truncate already accepted messages.
    }
};
