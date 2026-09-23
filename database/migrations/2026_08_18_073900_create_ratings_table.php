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
            Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('store_id')->constrained('stores', 'id')->onDelete('cascade');
            $table->foreignId('order_id')->constrained('orders', 'id')->onDelete('cascade');
            $table->tinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['customer_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
