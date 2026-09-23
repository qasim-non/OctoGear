<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin', function (Blueprint $table) {
            $table->id('employee_id');
            $table->string('name', 100);
            $table->enum('assigned_role', ['admin', 'manager', 'employee', 'hr', 'developer']);
            $table->string('mobile');
            $table->string('email', 255)->unique();
            $table->string('password');
            $table->enum('status', ['active', 'inactive', 'blocked']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin');
    }
};
