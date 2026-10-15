<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_offers', function (Blueprint $table) {
            $table->text('notes')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep the widened column to avoid truncating existing offer notes.
    }
};
