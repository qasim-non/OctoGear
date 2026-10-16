<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_requests', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();
            // NULL for processed requests. The unique key is the final guard
            // against two simultaneous pending changes to the same store.
            $table->foreignId('pending_store_id')->nullable()->unique()->constrained('stores')->restrictOnDelete();
            $table->text('change_reason')->nullable();
            $table->json('previous_details')->nullable();
            $table->char('base_version', 64)->nullable();
            $table->timestamp('processed_at')->nullable();
        });
        Schema::create('store_request_pictures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_request_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_request_pictures');
        Schema::table('store_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_store_id');
            $table->dropConstrainedForeignId('store_id');
            $table->dropColumn(['change_reason', 'previous_details', 'base_version', 'processed_at']);
        });
    }
};
