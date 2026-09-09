<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional human-readable label for the connection (e.g. "RX Mission
     * Heerlen"), shown in the connection UI and the request log. The runtime
     * connection name stays "gemeente_{id}"; this is display only.
     */
    public function up(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table) {
            $table->string('name')->nullable()->after('municipality_id');
        });
    }

    public function down(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
