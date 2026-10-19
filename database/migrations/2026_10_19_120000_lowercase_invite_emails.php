<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stores invite addresses in lowercase, as the invite models do for new rows.
 *
 * Invites created before the models normalised their address can still hold
 * mixed case, which no longer matches the lowercase address of the account the
 * invite belongs to.
 *
 * Each table has a unique index on the address (within the advisory or the
 * organisation for those two tables), so rows that only differ in case cannot
 * all be lowercased. This migration never deletes an invite that can still be
 * accepted:
 *
 * 1. Within such a group of rows, expired invites are deleted. They can no
 *    longer be accepted anyway.
 * 2. Every row whose lowercase address does not clash with another row is
 *    lowercased.
 * 3. Rows that still clash (more than one live invite for the same address)
 *    are left as they are and counted in the log. Picking which pending
 *    invite to drop is not something a migration should decide, and they
 *    expire on their own.
 */
return new class extends Migration
{
    /**
     * Invite tables and the columns their unique index scopes the address to.
     *
     * @var array<string, list<string>>
     */
    private const TABLES = [
        'admin_invites' => [],
        'advisory_invites' => ['advisory_id'],
        'municipality_invites' => [],
        'organisation_invites' => ['organisation_id'],
    ];

    public function up(): void
    {
        $expiredBefore = now()->subDays(config('invites.expiration_days'));

        foreach (self::TABLES as $table => $scope) {
            DB::transaction(function () use ($table, $scope, $expiredBefore) {
                $this->deleteExpiredCaseDuplicates($table, $scope, $expiredBefore);
                $this->lowercaseUnambiguousEmails($table, $scope);
                $this->logRemainingMixedCase($table);
            });
        }
    }

    public function down(): void
    {
        // The original casing is not kept, so there is nothing to restore.
    }

    /**
     * @param  list<string>  $scope
     */
    private function deleteExpiredCaseDuplicates(string $table, array $scope, DateTimeInterface $expiredBefore): void
    {
        DB::table($table)
            ->where('created_at', '<=', $expiredBefore)
            ->whereExists(fn (Builder $query) => $this->otherRowWithSameAddress($query, $table, $scope))
            ->delete();
    }

    /**
     * @param  list<string>  $scope
     */
    private function lowercaseUnambiguousEmails(string $table, array $scope): void
    {
        DB::table($table)
            ->whereRaw('email <> LOWER(email)')
            ->whereNotExists(fn (Builder $query) => $this->otherRowWithSameAddress($query, $table, $scope))
            ->update(['email' => DB::raw('LOWER(email)')]);
    }

    /**
     * Another row in the same table and scope whose address only differs in case.
     *
     * @param  list<string>  $scope
     */
    private function otherRowWithSameAddress(Builder $query, string $table, array $scope): void
    {
        $query->selectRaw('1')
            ->from($table, 'other')
            ->whereColumn('other.id', '<>', "{$table}.id")
            ->whereRaw("LOWER(other.email) = LOWER({$table}.email)");

        foreach ($scope as $column) {
            $query->whereColumn("other.{$column}", "{$table}.{$column}");
        }
    }

    private function logRemainingMixedCase(string $table): void
    {
        $remaining = DB::table($table)->whereRaw('email <> LOWER(email)')->count();

        if ($remaining > 0) {
            Log::warning('Invites left with a mixed case email address, because another live invite holds the same address', [
                'table' => $table,
                'count' => $remaining,
            ]);
        }
    }
};
