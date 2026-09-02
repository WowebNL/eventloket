<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-connection Eventloket behaviour toggles. These steer how Eventloket
     * presents and notifies on a zaak for a municipality that runs its own ZGW
     * backend (OpenZaak / RX Mission) and handles cases in its own system. The
     * defaults preserve the current behaviour, so the global "main" connection
     * (which has no row) is unaffected.
     */
    public function up(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table) {
            $table->boolean('lock_status_for_behandelaar')->default(false)->after('eigenschap_date_format');
            $table->boolean('show_besluiten_tab')->default(true)->after('lock_status_for_behandelaar');
            $table->boolean('show_bestanden_tab')->default(true)->after('show_besluiten_tab');
            $table->boolean('show_adviesvragen_tab')->default(true)->after('show_bestanden_tab');
            $table->boolean('show_organisatievragen_tab')->default(true)->after('show_adviesvragen_tab');
            $table->boolean('suppress_notifications')->default(false)->after('show_organisatievragen_tab');
        });
    }

    public function down(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table) {
            $table->dropColumn([
                'lock_status_for_behandelaar',
                'show_besluiten_tab',
                'show_bestanden_tab',
                'show_adviesvragen_tab',
                'show_organisatievragen_tab',
                'suppress_notifications',
            ]);
        });
    }
};
