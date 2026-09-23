<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the admin-review audit trail to store requests:
     * rejection_reason (why a request was denied) and
     * processed_by (which admin handled accept/reject).
     */
    public function up(): void
    {
        Schema::table('store_requests', function (Blueprint $table) {
            $table->string('rejection_reason', 255)->nullable()->after('request_status');
            $table->foreignId('processed_by')
                ->nullable()
                ->after('rejection_reason')
                ->constrained('admin', 'employee_id')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('store_requests', function (Blueprint $table) {
            $table->dropForeign(['processed_by']);
            $table->dropColumn(['rejection_reason', 'processed_by']);
        });
    }
};
