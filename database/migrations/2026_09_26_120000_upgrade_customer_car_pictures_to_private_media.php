<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_car_pictures', function (Blueprint $table) {
            // The historic column held arbitrary strings. A relative `path`
            // is meaningful only when a non-null private storage disk exists.
            $table->renameColumn('picture', 'path');
        });

        Schema::table('customer_car_pictures', function (Blueprint $table) {
            $table->string('disk', 64)->nullable()->after('path');
            $table->string('mime_type', 127)->nullable()->after('path');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('size_bytes');
            $table->index(['car_id', 'sort_order'], 'customer_car_pictures_car_sort_index');
        });

        Schema::table('customer_cars', function (Blueprint $table) {
            $table->uuid('idempotency_key')->nullable()->after('customer_id');
            $table->unique(
                ['customer_id', 'idempotency_key'],
                'customer_cars_customer_idempotency_key_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('customer_cars', function (Blueprint $table) {
            $table->dropUnique('customer_cars_customer_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('customer_car_pictures', function (Blueprint $table) {
            $table->dropIndex('customer_car_pictures_car_sort_index');
            $table->dropColumn([
                'disk',
                'mime_type',
                'size_bytes',
                'sort_order',
            ]);
            $table->renameColumn('path', 'picture');
        });
    }
};
