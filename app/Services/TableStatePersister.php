<?php

namespace App\Services;

use App\Models\TableState;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Mirrors Filament's per-table session state into the database, scoped to the
 * authenticated user, so filters/sort/search/columns survive a new session.
 *
 * Filament persists this state to the session when the matching
 * `->persist*InSession()` table options are enabled (see
 * `AppServiceProvider::boot()`). This service simply:
 *   - seed():     database -> session, before Filament reads the session
 *   - snapshot(): session -> database, after Filament has written to it
 *
 * All public entry points are fail-safe: persistence must never break a page.
 */
class TableStatePersister
{
    /**
     * Session-key resolver methods exposed by Filament's InteractsWithTable.
     * Each returns the unique session key for one slice of table state. The
     * filters key is additionally scoped per tenant (md5(class|tenant)) while
     * all other keys hash the component class only.
     *
     * @var list<string>
     */
    protected const KEY_METHODS = [
        'getTableFiltersSessionKey',
        'getTableSortSessionKey',
        'getTableSearchSessionKey',
        'getTableColumnSearchesSessionKey',
        'getTableColumnsSessionKey',
        'getHasReorderedTableColumnsSessionKey',
        'getTablePerPageSessionKey',
    ];

    /**
     * Whether this Livewire component is a Filament table we can persist.
     */
    public function appliesTo(object $component): bool
    {
        return method_exists($component, 'getTableFiltersSessionKey');
    }

    /**
     * Restore the user's saved state into the session for the keys this table
     * uses, without overwriting anything already present in the session.
     */
    public function seed(object $component): void
    {
        if (! $this->appliesTo($component)) {
            return;
        }

        try {
            $user = $this->user();

            if ($user === null) {
                return;
            }

            $record = $this->record($user, $this->tableKey($component));
            $stored = $record !== null ? $record->state : [];

            if ($stored === []) {
                return;
            }

            foreach ($this->sessionKeys($component) as $key) {
                if (array_key_exists($key, $stored) && ! session()->has($key)) {
                    session()->put($key, $stored[$key]);
                }
            }
        } catch (Throwable) {
            // Fail-safe: never break the page over persistence.
        }
    }

    /**
     * Persist the current session state for this table into the user's row,
     * merging with previously saved state. Only writes when changed.
     */
    public function snapshot(object $component): void
    {
        if (! $this->appliesTo($component)) {
            return;
        }

        try {
            $user = $this->user();

            if ($user === null) {
                return;
            }

            $current = [];

            foreach ($this->sessionKeys($component) as $key) {
                if (session()->has($key)) {
                    $current[$key] = session()->get($key);
                }
            }

            if ($current === []) {
                return;
            }

            $tableKey = $this->tableKey($component);
            $record = $this->record($user, $tableKey);
            $existing = $record !== null ? $record->state : [];
            $merged = array_merge($existing, $current);

            if ($merged == $existing) {
                return;
            }

            TableState::query()->updateOrCreate(
                ['user_id' => $user->id, 'table_key' => $tableKey],
                ['state' => $merged],
            );
        } catch (Throwable) {
            // Fail-safe: never break the page over persistence.
        }
    }

    /**
     * Resolve the session keys this component uses for its persisted state.
     *
     * @return list<string>
     */
    protected function sessionKeys(object $component): array
    {
        $keys = [];

        foreach (self::KEY_METHODS as $method) {
            if (! method_exists($component, $method)) {
                continue;
            }

            try {
                $key = $component->{$method}();
            } catch (Throwable) {
                // Key not resolvable yet (e.g. table not configured) - skip it.
                continue;
            }

            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    protected function record(User $user, string $tableKey): ?TableState
    {
        return TableState::query()
            ->where('user_id', $user->id)
            ->where('table_key', $tableKey)
            ->first();
    }

    /**
     * Stable per-table identifier: the Livewire component class hosting the
     * table. Session-key prefixes are unusable here - Filament hashes the
     * filters key per tenant but every other key per class, so under
     * multi-tenancy no single prefix identifies the table.
     */
    protected function tableKey(object $component): string
    {
        return $component::class;
    }

    protected function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
