<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The user who triggered the ZGW call, when there is an authenticated one.
     * Calls made from queued jobs or console commands run without a user, so
     * this stays null for the bulk of background traffic.
     */
    public function up(): void
    {
        Schema::table('zgw_request_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('municipality_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('zgw_request_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
