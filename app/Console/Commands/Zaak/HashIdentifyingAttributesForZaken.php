<?php

declare(strict_types=1);

namespace App\Console\Commands\Zaak;

use App\Jobs\Submit\HashIdentifyingAttributes;
use App\Models\Zaak;
use Illuminate\Console\Command;

/**
 * Dispatch HashIdentifyingAttributes for every zaak whose stored snapshot still
 * holds an identifying value in plain form.
 *
 * The submit chain hashes those values in its last job. A chain that stopped at
 * an earlier failing job never got there, so its zaak kept the plain values;
 * this command catches those up.
 *
 * Safe to run more than once: only zaken that still hold a plain value are
 * selected, and the job itself leaves an already hashed value alone. Recent
 * zaken are skipped (--older-than) so a chain that is still running keeps the
 * plain values its remaining jobs need.
 *
 * Deelzaken are left out unless asked for: they receive a copy of the snapshot
 * of their hoofdzaak, which the submit chain does not hash at all, so including
 * them changes existing deelzaken rather than finishing interrupted chains.
 */
class HashIdentifyingAttributesForZaken extends Command
{
    protected $signature = 'zaak:hash-identifying-attributes
        {--older-than=60 : Only zaken created at least this many minutes ago}
        {--include-deelzaken : Also hash the snapshot copies held by deelzaken}
        {--dry-run : Only count and list the zaken, dispatch nothing}';

    protected $description = 'Dispatch HashIdentifyingAttributes for zaken whose snapshot still holds a plain identifying value.';

    public function handle(): int
    {
        $olderThan = filter_var($this->option('older-than'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($olderThan === false) {
            $this->error('--older-than takes a whole number of minutes, 0 or more.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $includeDeelzaken = (bool) $this->option('include-deelzaken');

        $query = Zaak::query()
            ->whereNotNull('form_state_snapshot')
            ->where('created_at', '<=', now()->subMinutes($olderThan))
            ->unless($includeDeelzaken, fn ($query) => $query->whereNull('hoofdzaak_id'));

        $matched = 0;

        foreach ($query->lazyById(200) as $zaak) {
            /** @var Zaak $zaak */
            if (! HashIdentifyingAttributes::hasUnhashedValues($zaak->form_state_snapshot)) {
                continue;
            }

            $matched++;

            // Only the id and the public number: the values themselves never
            // go to the console.
            $this->line("  - zaak {$zaak->id} ({$zaak->public_id})");

            if (! $dryRun) {
                HashIdentifyingAttributes::dispatch($zaak);
            }
        }

        $this->info($dryRun
            ? "{$matched} zaak/zaken with a plain identifying value; nothing dispatched (dry run)."
            : "Dispatched HashIdentifyingAttributes for {$matched} zaak/zaken.");

        return self::SUCCESS;
    }
}
