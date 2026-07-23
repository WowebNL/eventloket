<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether an organiser may withdraw ("intrekken") a zaak from inside
     * Eventloket. The default preserves the current behaviour (withdrawal
     * allowed). It must be turned off for a OneGround (RX Mission) connection:
     * there, setting the eind-status archives the zaak immediately and OneGround
     * rejects it unless all related documents are already 'gearchiveerd', so
     * the withdrawal flow cannot complete.
     */
    public function up(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table) {
            $table->boolean('allow_organiser_withdrawal')->default(true)->after('suppress_notifications');
        });
    }

    public function down(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table) {
            $table->dropColumn('allow_organiser_withdrawal');
        });
    }
};
