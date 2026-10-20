<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('conversations')->select('customer_id', 'provider_id')
            ->groupBy('customer_id', 'provider_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicates) {
            throw new RuntimeException(
                'Duplicate customer/provider conversations exist. Approve and complete history consolidation before retrying this migration. No conversations or messages were changed.',
            );
        }
        Schema::table('conversations', function (Blueprint $table) {
            $table->unique(['customer_id', 'provider_id'], 'conversations_customer_provider_unique');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique('conversations_customer_provider_unique');
        });
    }
};
