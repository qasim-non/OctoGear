<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_image_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 64);
            $table->string('path');
            $table->unique(['disk', 'path']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_image_deletions');
    }
};
