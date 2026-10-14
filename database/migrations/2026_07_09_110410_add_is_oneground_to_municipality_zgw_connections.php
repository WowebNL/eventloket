<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a connection as a OneGround (RX Mission) backend. OneGround deviates
     * from the ZGW standard on a few points, so this flag drives OneGround-
     * specific behaviour:
     *  - the GlobaleLocatie zaakobject is sent with `overigeData` as a bare
     *    string instead of the standard free-form object, and
     *  - organiser withdrawal ("intrekken") is blocked, because setting the
     *    eind-status archives the zaak immediately and OneGround rejects that
     *    unless all related documents are already 'gearchiveerd'.
     *
     * Defaults to false so existing (OpenZaak) connections keep standard
     * behaviour.
     */
    public function up(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table) {
            $table->boolean('is_oneground')->default(false)->after('allow_organiser_withdrawal');
        });
    }

    public function down(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table) {
            $table->dropColumn('is_oneground');
        });
    }
};
