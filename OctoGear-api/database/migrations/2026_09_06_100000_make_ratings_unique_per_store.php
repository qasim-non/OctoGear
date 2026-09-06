<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer may rate a store only once — portfolio the uniqueness
     * from (customer_id, order_id) to (customer_id, store_id).
     */
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropUnique('ratings_customer_id_order_id_unique');
            $table->unique(['customer_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropUnique('ratings_customer_id_store_id_unique');
            $table->unique(['customer_id', 'order_id']);
        });
    }
};
