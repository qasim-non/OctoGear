<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_offer_id')->constrained('order_offers')->cascadeOnDelete();
            $table->string('disk', 64);
            $table->string('path');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedSmallInteger('sort_order');
            $table->unique(['order_offer_id', 'sort_order']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_images');
    }
};
