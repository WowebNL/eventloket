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
        Schema::table('event_form_drafts', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'organisation_id']);
            $table->index(['user_id', 'organisation_id']);
            $table->string('name')->nullable()->after('state');
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
