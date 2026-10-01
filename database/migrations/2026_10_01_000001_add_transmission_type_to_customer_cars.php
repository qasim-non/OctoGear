<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_cars', function (Blueprint $table) {
            // Historical cars have no recorded transmission; never guess it.
            $table->string('transmission_type', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_cars', function (Blueprint $table) {
            $table->dropColumn('transmission_type');
        });
    }
};
