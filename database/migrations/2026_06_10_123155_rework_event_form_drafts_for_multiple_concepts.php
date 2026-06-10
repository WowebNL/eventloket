<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sta meerdere concepten per (user, organisation) toe. De unique
     * constraint verdwijnt; een gewone index komt ervoor terug zodat
     * de lijst-query performant blijft. `name` is een gedenormaliseerde
     * weergavenaam, bij elke save afgeleid uit het evenementnaam-veld.
     */
    public function up(): void
    {
        // Voeg eerst de vervangende index + kolom toe. Op MySQL fungeert de
        // bestaande unique-index (user_id, organisation_id) tegelijk als
        // backing-index voor de user_id foreign key (leftmost prefix).
        // Zonder een alternatieve index weigert MySQL die unique te droppen
        // (error 1553: needed in a foreign key constraint). PostgreSQL heeft
        // dat probleem niet, vandaar dat het lokaal wel werkte. Door de
        // index vóór de drop aan te maken blijft de FK op beide drivers
        // backed.
        Schema::table('event_form_drafts', function (Blueprint $table) {
            $table->index(['user_id', 'organisation_id']);
            $table->string('name')->nullable()->after('state');
        });

        Schema::table('event_form_drafts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'organisation_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * NB: de unique constraint wordt bewust niet teruggezet — met
     * meerdere concepten per gebruiker zou dat falen. Een rollback
     * houdt dus de niet-unieke index.
     */
    public function down(): void
    {
        Schema::table('event_form_drafts', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
