<?php

namespace App\Console\Commands\Archiving;

use App\Enums\DestructionItemStatus;
use App\Enums\ZaakDestructionSource;
use App\Models\Archiving\DestructionListItem;
use App\Models\Zaak;
use App\Services\Archiving\EventloketDataDestroyer;
use App\Services\Archiving\ZaakDestructionService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Catches the zaken whose destroy notification never arrived.
 *
 * Eventloket's own data is destroyed on the strength of a ZGW notification, so
 * a lost one (an expired abonnement, a webhook outage, a dropped job) would
 * leave the personal data of an already destroyed zaak sitting here
 * indefinitely, with the zaakdata gone and nothing left to prompt a retry.
 *
 * The anchor is the destruction list: an item marked deleted whose local zaak
 * is still here is, by definition, one where the second half did not happen.
 * That keeps this cheap and precise — one ZGW read per suspect zaak, not a
 * sweep of every zaak in the system.
 *
 * It only covers zaken destroyed through our own archive module. A zaak
 * destroyed by a municipality in its own instance has no such anchor; there
 * the webhook is the only signal, which is why its health is worth watching.
 */
class ReconcileDestroyedZaken extends Command
{
    protected $signature = 'archiving:reconcile-destroyed-zaken
                            {--hours=6 : Only consider items destroyed at least this many hours ago}
                            {--dry-run : Report what would be cleaned up without touching anything}';

    protected $description = 'Clean up Eventloket data of destroyed zaken whose ZGW destroy notification never arrived';

    public function handle(ZaakDestructionService $zgw, EventloketDataDestroyer $destroyer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        // A grace period: the notification normally lands within seconds, and
        // racing it would only duplicate work the webhook is about to do.
        $before = now()->subHours((int) $this->option('hours'));

        $items = DestructionListItem::query()
            ->where('status', DestructionItemStatus::Deleted)
            ->where('destroyed_at', '<=', $before)
            ->whereNotNull('zaak_id')
            ->get();

        $reconciled = 0;

        foreach ($items as $item) {
            /** @var ?Zaak $zaak */
            $zaak = $item->zaak()->withTrashed()->first();

            if ($zaak === null) {
                continue;
            }

            // Confirm the zaak really is gone before destroying anything. A
            // still-present zaak means the item is wrong, not the notification.
            try {
                if ($zgw->fetchZaak($item->zgw_zaak_url) !== null) {
                    $this->warn("Zaak [{$item->zaaknummer}] is marked destroyed but still exists in ZGW; left alone.");

                    continue;
                }
            } catch (Throwable $exception) {
                report($exception);

                $this->warn("Could not verify zaak [{$item->zaaknummer}]: {$exception->getMessage()}");

                continue;
            }

            if ($dryRun) {
                $this->line("Would destroy the Eventloket data of zaak [{$item->zaaknummer}].");
                $reconciled++;

                continue;
            }

            $destroyer->destroy($zaak, ZaakDestructionSource::Reconciliation);
            $reconciled++;

            $this->info("Destroyed the Eventloket data of zaak [{$item->zaaknummer}] (no destroy notification received).");
        }

        if ($reconciled === 0) {
            $this->info('Nothing to reconcile.');
        }

        return self::SUCCESS;
    }
}
