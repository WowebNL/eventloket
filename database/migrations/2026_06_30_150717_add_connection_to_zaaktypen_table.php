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
            // The ZGW connection a zaaktype row was synced from: "main" for the
            // shared catalogus, "gemeente_{id}" for a municipality's own instance.
            // It is the authoritative source for catalogus reads and zaak creation.
            $table->string('connection')->default('main')->after('zgw_zaaktype_url');
            $table->index(['connection', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zaaktypen', function (Blueprint $table) {
            $table->dropIndex(['connection', 'is_active']);
            $table->dropColumn('connection');
        });
    }
};
