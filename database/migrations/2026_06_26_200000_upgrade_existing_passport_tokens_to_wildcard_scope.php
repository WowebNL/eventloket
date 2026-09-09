<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Upgrade all existing active Passport tokens to the wildcard scope.
     *
     * Before this migration, tokens were issued without any scope (scopes = []).
     * After adding scope-based route protection (api:access / notifications:receive),
     * empty-scoped tokens would receive 403 on every protected route. This upgrades
     * them to ['*'] so they keep the full access they had before.
     *
     * Only non-revoked tokens are upgraded. Expired tokens are left untouched since
     * they are no longer usable anyway.
     */
    public function up(): void
    {
        DB::table('oauth_access_tokens')
            ->where('revoked', false)
            ->where(fn ($query) => $query->whereNull('scopes')->orWhereJsonLength('scopes', 0))
            ->update(['scopes' => json_encode(['*'])]);
    }

    public function down(): void
    {
        // Intentionally not reversible: we cannot know which tokens originally had
        // empty scopes versus which ones were legitimately granted ['*'].
    }
};
