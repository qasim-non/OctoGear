<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_cars', function (Blueprint $table) {
            // The server-only digest detects a same-key request whose payload
            // changed. It is deliberately never returned by a resource.
            $table->string('idempotency_fingerprint', 64)
                ->nullable()
                ->after('idempotency_key');
            $table->index(
                ['created_at', 'idempotency_key'],
                'customer_cars_idempotency_expiry_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('customer_cars', function (Blueprint $table) {
            $table->dropIndex('customer_cars_idempotency_expiry_index');
            $table->dropColumn('idempotency_fingerprint');
        });
    }
};
