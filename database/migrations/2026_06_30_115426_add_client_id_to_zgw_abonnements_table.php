<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track the Passport client that minted the current webhook token, so the
     * auto-renew and re-registration paths can delete the previous client after a
     * successful rotation instead of leaving orphaned client_credentials clients
     * behind.
     */
    public function up(): void
    {
        Schema::table('zgw_abonnements', function (Blueprint $table) {
            $table->string('client_id')->nullable()->after('token_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zgw_abonnements', function (Blueprint $table) {
            $table->dropColumn('client_id');
        });
    }
};
