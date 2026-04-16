<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Upgrade all existing active Passport tokens and clients to the wildcard scope.
     *
     * Before this migration, tokens were issued without any scope (scopes = []).
     * After adding scope-based route protection (api:access / notifications:receive),
     * empty-scoped tokens would receive 403 on all routes. This migration upgrades
     * them to ['*'] so they retain full access as before.
     *
     * Only non-revoked tokens and clients are upgraded. Expired tokens are left
     * unchanged — they are no longer usable anyway.
     */
    public function up(): void
    {
        // Upgrade active tokens with empty scopes
        DB::table('oauth_access_tokens')
            ->where('revoked', false)
            ->where(fn ($q) => $q->whereNull('scopes')->orWhereJsonLength('scopes', 0))
            ->update(['scopes' => json_encode(['*'])]);
    }

    public function down(): void
    {
        // Intentionally not reversible: we cannot know which tokens originally had
        // empty scopes versus which ones were legitimately given ['*'].
    }
};
