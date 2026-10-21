<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin', function (Blueprint $table) {
            $table->string('preferred_language', 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('admin', fn (Blueprint $table) => $table->dropColumn('preferred_language'));
    }
};
