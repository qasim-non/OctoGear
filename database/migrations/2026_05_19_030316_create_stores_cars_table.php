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
        Schema::create('stores_cars', function (Blueprint $table) {
            $table->id();
            $table->integer('manufacturing_year');
            $table->string('vehicle_plat_number', 50);
            $table->foreignId('car_name_id')->constrained('cars_names', 'id')->onDelete('cascade');
            $table->foreignId('color_id')->constrained('colors', 'id')->onDelete('cascade');
            $table->foreignId('store_id')->constrained('stores', 'id')->onDelete('cascade');
            $table->foreignId('fuel_type')->constrained('fuel_types', 'id')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores_cars');
    }
};
