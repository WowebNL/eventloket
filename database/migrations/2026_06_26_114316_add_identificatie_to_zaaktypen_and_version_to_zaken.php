<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('zaaktypen', function (Blueprint $table) {
            // The logical ZGW zaaktype identifier. A single identificatie covers all
            // versions of a zaaktype; the local row keeps the latest version url in
            // zgw_zaaktype_url and resolves the version valid on the creation date at
            // zaak-creation time. Nullable until the backfill command has run.
            $table->string('identificatie')->nullable()->after('name')->index();
        });

        // zgw_zaaktype_url is no longer one-row-per-version, so it is no longer unique.
        // The logical key is (identificatie, municipality_id), enforced by the sync logic.
        Schema::table('zaaktypen', function (Blueprint $table) {
            $table->dropUnique('zaaktypen_zgw_zaaktype_url_unique');
            $table->index('zgw_zaaktype_url');
        });

        Schema::table('zaken', function (Blueprint $table) {
            // Snapshot of the exact zaaktype version url the zaak was created against,
            // so per-version reads (document types, resultaattypen, statustypen) stay
            // correct even after the logical zaaktype row advances to a newer version.
            // Nullable: older rows fall back to the version on the ZGW zaak DTO.
            $table->string('zgw_zaaktype_url')->nullable()->after('zaaktype_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zaken', function (Blueprint $table) {
            $table->dropColumn('zgw_zaaktype_url');
        });

        Schema::table('zaaktypen', function (Blueprint $table) {
            $table->dropIndex(['zgw_zaaktype_url']);
            $table->unique('zgw_zaaktype_url');
            $table->dropIndex(['identificatie']);
            $table->dropColumn('identificatie');
        });
    }
};
