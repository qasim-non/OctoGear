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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->enum('order_type', ['general', 'specific']);
            $table->integer('quantity');
            $table->enum('status', [
                'pending',          // customer sent request, waiting store response.
                'rejected',         // store rejected (just for specific order)
                'awaiting_payment', // customer selected the store's final offer and must pay
                'paid',             // customer paid
                'completed',        // customer received component
                'cancelled',        // customer cancelled
            ]);
            $table->integer('offered_price')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('customer_id')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('store_car_component_id')->nullable()->constrained('store_car_components', 'id')->onDelete('cascade');
            $table->foreignId('component_id')->nullable()->constrained('components')->restrictOnDelete();
            $table->string('component_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
