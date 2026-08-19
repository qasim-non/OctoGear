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
            Schema::create('store_car_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_car_id')->constrained('stores_cars', 'id')->onDelete('cascade');
            $table->foreignId('component_id')->constrained('components', 'id')->onDelete('cascade');
            $table->string('part_number', 50)->nullable();
            $table->text('description');
            $table->integer('price');
            $table->integer('stock_quantity')->default(0);
            $table->integer('warranty_months')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_car_id', 'component_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_car_components');
    }
};
