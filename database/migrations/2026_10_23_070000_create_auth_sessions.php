<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->morphs('actor');
            $table->boolean('browser')->default(false);
            $table->unsignedBigInteger('channel_token_id')->nullable()->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignUuid('auth_session_id')->nullable()->constrained('auth_sessions')->cascadeOnDelete();
        });
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('auth_session_id')->constrained('auth_sessions')->cascadeOnDelete();
            $table->char('token_hash', 64);
            $table->timestamp('used_at')->nullable();
            $table->uuid('request_id')->nullable();
            $table->uuid('replacement_id')->nullable();
            $table->text('retry_response')->nullable();
            $table->timestamp('retry_expires_at')->nullable()->index();
            $table->timestamps();
        });
        // Bound existing credentials without deleting retained application data.
        $cutoff = now()->addDays(config('auth_sessions.session_days'));
        DB::table('personal_access_tokens')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $cutoff))
            ->update(['expires_at' => $cutoff]);
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('auth_session_id');
        });
        Schema::dropIfExists('auth_sessions');
    }
};
