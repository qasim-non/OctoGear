<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('temporary_password_expires_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedInteger('security_version')->default(1);
        });
        Schema::create('admin_security_locks', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
        });
        DB::table('admin_security_locks')->insert(['id' => 1]);
        Schema::create('admin_password_challenges', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_id')->primary();
            $table->foreign('admin_id')->references('employee_id')->on('admin');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
        });
        Schema::create('admin_security_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('admin_id')->index();
            $table->string('action', 50);
            $table->json('details')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_security_events');
        Schema::dropIfExists('admin_password_challenges');
        Schema::dropIfExists('admin_security_locks');
        Schema::table('admin', fn (Blueprint $table) => $table->dropColumn(['must_change_password', 'temporary_password_expires_at', 'password_changed_at', 'last_login_at', 'security_version']));
    }
};
