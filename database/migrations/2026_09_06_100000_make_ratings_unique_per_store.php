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
        // 1. Add a standard index so the foreign key has an index to rely on
        $table->index('customer_id');

        // 2. Now you can safely drop the old unique index
        $table->dropUnique('ratings_customer_id_order_id_unique');

        // 3. Add the new unique index
        $table->unique(['customer_id', 'store_id']);
    });
    }

    /**
     * Reverse the migrations.
     */
   public function down(): void
{
    Schema::table('ratings', function (Blueprint $table) {
        // 1. Add back the old unique index first (protects the foreign key)
        $table->unique(['customer_id', 'order_id']);

        // 2. Drop the new unique index
        $table->dropUnique('ratings_customer_id_store_id_unique');

        // 3. Drop the regular index we added in up()
        $table->dropIndex(['customer_id']);
    });
}
};
