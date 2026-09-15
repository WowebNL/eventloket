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
            // The logical Eventloket role this zaaktype fulfils (vergunning,
            // melding, vooraankondiging, doorkomst). Drives the role-based
            // filters and zaaktype resolution; nullable until classified.
            $table->string('role')->nullable()->after('name');
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zaaktypen', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
