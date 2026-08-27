<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist the origin of a prefilled concept ("Nieuwe aanvraag met
     * deze gegevens" / "Definitieve aanvraag indienen"). Before this the
     * only trace of the source zaak was the transient `?bron=hergebruik`
     * query param, so at submit time nobody could tell which zaak a
     * concept was created from.
     */
    public function up(): void
    {
        Schema::table('event_form_drafts', function (Blueprint $table) {
            $table->foreignUuid('source_zaak_id')->nullable()->after('organisation_id')->constrained('zaken')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_form_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_zaak_id');
        });
    }
};
