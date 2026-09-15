<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table): void {
            // Timestamp of when a connection was explicitly activated by a
            // KoppelingBeheerder. Null means the connection is not live: the
            // resolver ignores it and routes to the "main" connection until it
            // is verified and activated.
            $table->timestamp('activated_at')->nullable()->after('last_verified_at');
        });

        // Backfill: a connection that was previously verified was already in
        // use, so keep it live after this migration. Never-verified rows become
        // inactive, which is the intended new default.
        DB::table('municipality_zgw_connections')
            ->whereNotNull('last_verified_at')
            ->update(['activated_at' => DB::raw('last_verified_at')]);
    }

    public function down(): void
    {
        Schema::table('municipality_zgw_connections', function (Blueprint $table): void {
            $table->dropColumn('activated_at');
        });
    }
};
