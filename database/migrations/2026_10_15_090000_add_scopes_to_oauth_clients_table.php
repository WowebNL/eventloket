<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passport can restrict a client to a list of scopes, but the column that
     * holds the list is opt-in and neither Passport's own migration nor ours
     * created it. Adding it is what lets the ZGW webhook credential be minted
     * for a single scope.
     *
     * Nullable on purpose: existing clients keep their current behaviour, and
     * only clients minted with an explicit list are restricted by it.
     */
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->text('scopes')->nullable()->after('grant_types');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn('scopes');
        });
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
