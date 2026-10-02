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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('accepted_store_id')->nullable()->constrained('stores', 'id')->onDelete('set null');
            $table->foreignId('accepted_offer_id')->nullable()->constrained('order_offers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['accepted_store_id']);
            $table->dropForeign(['accepted_offer_id']);
            $table->dropColumn(['accepted_store_id', 'accepted_offer_id']);
        });
    }
};
