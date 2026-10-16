<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_requests', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable();
            $table->char('idempotency_fingerprint', 64)->nullable();
            $table->unique(['user_id', 'idempotency_key'], 'store_requests_user_request_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('store_requests', function (Blueprint $table) {
            $table->dropUnique('store_requests_user_request_key_unique');
            $table->dropColumn(['idempotency_key', 'idempotency_fingerprint']);
        });
    }
};
