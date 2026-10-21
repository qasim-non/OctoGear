<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Serializes catalog administration, including creation into an empty catalog.
        Schema::create('vehicle_catalog_locks', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('vehicle_catalog_locks')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_catalog_locks');
    }
};
