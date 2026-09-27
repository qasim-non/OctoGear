<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['store_pictures' => 'store_id', 'store_car_pictures' => 'car_id'] as $name => $parent) {
            Schema::table($name, function (Blueprint $table) {
                $table->renameColumn('picture', 'path');
            });
            Schema::table($name, function (Blueprint $table) use ($parent) {
                // Keep historical strings, but never treat them as trusted files
                // until an operator imports them onto a configured private disk.
                $table->string('disk', 64)->nullable();
                $table->string('mime_type', 127)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->index([$parent, 'sort_order']);
            });
        }

        foreach (['stores', 'store_requests', 'orders'] as $name) {
            $prefix = $name === 'orders' ? 'customer_image' : 'commercial_registration';
            $oldColumn = $name === 'orders' ? 'customer_image' : 'commercial_registration_picture';
            Schema::table($name, function (Blueprint $table) use ($oldColumn, $prefix) {
                $table->renameColumn($oldColumn, $prefix.'_path');
            });
            Schema::table($name, function (Blueprint $table) use ($prefix) {
                $table->string($prefix.'_disk', 64)->nullable();
                $table->string($prefix.'_mime_type', 127)->nullable();
                $table->unsignedBigInteger($prefix.'_size_bytes')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['stores', 'store_requests', 'orders'] as $name) {
            $prefix = $name === 'orders' ? 'customer_image' : 'commercial_registration';
            $oldColumn = $name === 'orders' ? 'customer_image' : 'commercial_registration_picture';
            Schema::table($name, function (Blueprint $table) use ($oldColumn, $prefix) {
                $table->dropColumn([$prefix.'_disk', $prefix.'_mime_type', $prefix.'_size_bytes']);
                $table->renameColumn($prefix.'_path', $oldColumn);
            });
        }

        foreach (['store_pictures' => 'store_id', 'store_car_pictures' => 'car_id'] as $name => $parent) {
            Schema::table($name, function (Blueprint $table) use ($parent) {
                $table->dropIndex([$parent, 'sort_order']);
                $table->dropColumn(['disk', 'mime_type', 'size_bytes', 'sort_order']);
                $table->renameColumn('path', 'picture');
            });
        }
    }
};
