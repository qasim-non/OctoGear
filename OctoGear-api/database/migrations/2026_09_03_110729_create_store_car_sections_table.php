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
        Schema::create('store_car_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_car_id')->constrained('stores_cars', 'id')->onDelete('cascade');
            $table->foreignId('section_id')->constrained('car_sections', 'id')->onDelete('cascade');
            $table->enum('condition', ['okay', 'damaged']);
            $table->timestamps();

            $table->unique(['store_car_id', 'section_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_car_sections');
    }
};
