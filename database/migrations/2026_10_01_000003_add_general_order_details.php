<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_vehicle_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_car_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('car_name_id')->nullable()->constrained('cars_names')->nullOnDelete();
            $table->foreignId('car_company_id')->nullable()->constrained('cars_companies')->nullOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('colors')->nullOnDelete();
            $table->foreignId('fuel_type')->nullable()->constrained('fuel_types')->nullOnDelete();
            $table->string('car_name_en');
            $table->string('car_name_ar');
            $table->string('company_name_en');
            $table->string('company_name_ar');
            $table->string('color_name_en');
            $table->string('color_name_ar');
            $table->string('fuel_type_en');
            $table->string('fuel_type_ar');
            $table->unsignedSmallInteger('manufacturing_year');
            $table->string('transmission_type', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_vehicle_details');
    }
};
