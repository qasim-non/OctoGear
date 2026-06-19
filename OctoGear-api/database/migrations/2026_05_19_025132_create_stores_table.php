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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('mobile', 15)->unique();
            $table->string('nick_name', 100);
            $table->string('emploee_name', 100);
            $table->string('url_location', 255);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('commerical_registration_number', 50);
            $table->string('commerical_registration_picture', 255);
            $table->foreignId('city_id')->constrained('cities', 'id')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
