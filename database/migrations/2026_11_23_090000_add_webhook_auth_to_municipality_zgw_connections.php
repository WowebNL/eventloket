<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which kind of credential this connection puts in the `auth` field of its
     * Notificaties abonnement: "passport" (a scoped Passport access token) or
     * "static" (a short opaque token, for Notificaties APIs that limit the length
     * of that field). Empty means "passport", so every existing connection keeps
     * its current behaviour. A change only takes effect when the abonnement is
     * registered again.
     */
    public function up(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table) {
            $table->string('webhook_auth', 16)->nullable()->after('notificaties_url');
        });
    }

    public function down(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table) {
            $table->dropColumn('webhook_auth');
        });
    }
};
