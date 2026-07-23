<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The informatieobjecttype for the aanvraagformulier PDF, separate from the
     * bijlage type. Nullable: when unset the PDF keeps the historical first-type
     * fallback, so behaviour is unchanged until a beheerder maps it.
     */
    public function up(): void
    {
        Schema::table('municipality_zaaktype_mappings', function (Blueprint $table) {
            $table->string('aanvraag_informatieobjecttype')->nullable()->after('bijlage_informatieobjecttype');
        });
    }

    public function down(): void
    {
        Schema::table('municipality_zaaktype_mappings', function (Blueprint $table) {
            $table->dropColumn('aanvraag_informatieobjecttype');
        });
    }
};
