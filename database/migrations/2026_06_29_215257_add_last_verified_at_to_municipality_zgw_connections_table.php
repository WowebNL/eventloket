<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table): void {
            // Timestamp of the last fully successful connection verification
            // (run from the "Verbinding testen" modal). Null means never checked.
            $table->timestamp('last_verified_at')->nullable()->after('suppress_notifications');
        });
    }

    public function down(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table): void {
            $table->dropColumn('last_verified_at');
        });
    }
};
