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
            Schema::create('store_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'id')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('mobile', 15);
            $table->string('nick_name', 100);
            $table->string('employee_name', 100);
            $table->string('url_location', 255);
            $table->string('commercial_registration_number', 50);
            $table->string('commercial_registration_picture', 255);
            $table->foreignId('city_id')->constrained('cities', 'id')->onDelete('cascade');
            $table->enum('request_status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_requests');
    }
};
