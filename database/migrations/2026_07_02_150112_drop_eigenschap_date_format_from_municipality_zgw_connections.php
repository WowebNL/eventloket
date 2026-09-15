<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-connection eigenschap_date_format is obsolete: zaakeigenschap dates
 * are now formatted from the catalogus eigenschap's specificatie.formaat
 * (datum -> Ymd, datum_tijd -> YmdHis), so no per-connection override is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table): void {
            $table->dropColumn('eigenschap_date_format');
        });
    }

    public function down(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table): void {
            $table->string('eigenschap_date_format')->nullable()->after('vertrouwelijkheid_map');
        });
    }
};
