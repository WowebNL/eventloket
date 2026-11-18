<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A per-connection opt-in that lets advisories with access to all zaken
     * read (view and download, never upload) the documents of a zaak while the
     * bestanden tab is switched off for that connection. It only means
     * something while the tab is off, so it defaults to off and every existing
     * row keeps its current behaviour.
     */
    public function up(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table) {
            $table->boolean('show_bestanden_tab_read_only_for_advisories')->default(false)->after('show_bestanden_tab');
        });

        // The model switches the opt-in off whenever the tab is switched on (see
        // MunicipalityZgwConnectionObserver). This constraint is the safety net
        // for writes that bypass the model: such a write fails instead of
        // leaving a row that combines the tab with the read-only opt-in.
        DB::statement('ALTER TABLE municipality_zgw_connections ADD CONSTRAINT municipality_zgw_connections_read_only_docs_need_tab_off CHECK (NOT (show_bestanden_tab AND show_bestanden_tab_read_only_for_advisories))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE municipality_zgw_connections DROP CONSTRAINT municipality_zgw_connections_read_only_docs_need_tab_off');

        Schema::table('municipality_zgw_connections', function (Blueprint $table) {
            $table->dropColumn('show_bestanden_tab_read_only_for_advisories');
        });
    }
};
