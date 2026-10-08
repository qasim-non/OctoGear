<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('token', 512)->change();
            $table->foreignId('personal_access_token_id')->nullable()->unique()
                ->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->string('locale', 2)->default('ar');
            $table->timestamp('last_seen_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropForeign(['personal_access_token_id']);
            $table->dropUnique(['personal_access_token_id']);
            $table->dropIndex(['last_seen_at']);
            $table->dropColumn(['personal_access_token_id', 'locale', 'last_seen_at']);
        });
        // Retain the widened token column: rolling back must not truncate tokens.
    }
};
