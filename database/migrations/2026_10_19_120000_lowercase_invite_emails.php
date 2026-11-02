<?php

use Illuminate\Database\Migrations\Migration;
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
 *
 * The lowercase address and the grouping are computed in PHP with
 * strtolower(), the function the models use, instead of in SQL. That keeps
 * the result identical on every supported database: PostgreSQL's and MySQL's
 * LOWER() also fold non-ASCII letters, MySQL has no translate(), and MySQL's
 * default collations compare case and accent insensitively. The database only
 * receives plain lookups, and deletes and updates by primary key. The invite
 * tables are small, so reading their addresses into memory is cheap.
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

    private const CHUNK_SIZE = 1000;

    public function up(): void
    {
        $expiredBefore = now()->subDays(config('invites.expiration_days'));

        foreach (self::TABLES as $table => $scope) {
            DB::transaction(fn () => $this->normalise($table, $scope, $expiredBefore));
        }
    }

    public function down(): void
    {
        // The original casing is not kept, so there is nothing to restore.
    }

    /**
     * @param  list<string>  $scope
     */
    private function normalise(string $table, array $scope, DateTimeInterface $expiredBefore): void
    {
        $groups = $this->groupByLowercaseAddress($table, $scope);

        $expired = $this->expiredIds($table, $this->idsInClashingGroups($groups), $expiredBefore);

        foreach (array_chunk($expired, self::CHUNK_SIZE) as $ids) {
            DB::table($table)->whereIn('id', $ids)->delete();
        }

        $expired = array_flip($expired);
        $remainingMixedCase = 0;

        foreach ($groups as $group) {
            $rows = array_filter($group, fn (array $row): bool => ! isset($expired[$row['id']]));

            if (count($rows) === 1) {
                $row = reset($rows);

                if ($row['email'] !== strtolower($row['email'])) {
                    DB::table($table)->where('id', $row['id'])->update(['email' => strtolower($row['email'])]);
                }

                continue;
            }

            foreach ($rows as $row) {
                if ($row['email'] !== strtolower($row['email'])) {
                    $remainingMixedCase++;
                }
            }
        }

        if ($remainingMixedCase > 0) {
            Log::warning('Invites left with a mixed case email address, because another live invite holds the same address', [
                'table' => $table,
                'count' => $remainingMixedCase,
            ]);
        }
    }

    /**
     * The rows of a table grouped by their scope and lowercase address, so each
     * group holds the rows that would collide on the unique index once
     * lowercased.
     *
     * @param  list<string>  $scope
     * @return array<string, list<array{id: int, email: string}>>
     */
    private function groupByLowercaseAddress(string $table, array $scope): array
    {
        $groups = [];

        $rows = DB::table($table)
            ->select(['id', 'email', ...$scope])
            ->lazyById(self::CHUNK_SIZE);

        foreach ($rows as $row) {
            $groups[$this->groupKey($row, $scope)][] = [
                'id' => (int) $row->id,
                'email' => (string) $row->email,
            ];
        }

        return $groups;
    }

    /**
     * @param  list<string>  $scope
     */
    private function groupKey(object $row, array $scope): string
    {
        $parts = [];

        foreach ($scope as $column) {
            // A unique index never matches on NULL, so such a row cannot
            // collide with another one.
            if ($row->{$column} === null) {
                return 'row:'.$row->id;
            }

            $parts[] = (string) $row->{$column};
        }

        $parts[] = strtolower((string) $row->email);

        return json_encode($parts, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, list<array{id: int, email: string}>>  $groups
     * @return list<int>
     */
    private function idsInClashingGroups(array $groups): array
    {
        $ids = [];

        foreach ($groups as $group) {
            if (count($group) > 1) {
                array_push($ids, ...array_column($group, 'id'));
            }
        }

        return $ids;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function expiredIds(string $table, array $ids, DateTimeInterface $expiredBefore): array
    {
        $expired = [];

        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            $found = DB::table($table)
                ->whereIn('id', $chunk)
                ->where('created_at', '<=', $expiredBefore)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            array_push($expired, ...$found);
        }

        return $expired;
    }
};
