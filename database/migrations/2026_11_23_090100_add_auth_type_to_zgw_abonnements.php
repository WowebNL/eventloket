<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The kind of credential the abonnement was registered with. Every existing
     * abonnement carries a Passport access token, hence the default.
     */
    public function up(): void
    {
        Schema::table('zgw_abonnements', function (Blueprint $table) {
            $table->string('auth_type', 16)->default('passport')->after('abonnement_url');
        });
    }

    public function down(): void
    {
        Schema::table('zgw_abonnements', function (Blueprint $table) {
            $table->dropColumn('auth_type');
        });
    }
};
