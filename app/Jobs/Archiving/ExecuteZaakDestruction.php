<?php

namespace App\Jobs\Archiving;

use App\Enums\DestructionItemStatus;
use App\Models\Archiving\DestructionListItem;
use App\Models\Zaak;
use App\Services\Archiving\ZaakDestructionService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Destroys the zaakdata of a single zaak in OpenZaak: its besluiten, its
 * documents and the zaak itself.
 *
 * Only the ZGW side. Eventloket's own data about the zaak is destroyed by
 * {@see ZaakDestroyNotificationReceived}, triggered by the
 * destroy notification this job's zaak delete produces — the same route a
 * municipality's own ZGW instance takes.
 *
 * Idempotent: a retry after a partial failure resumes where it left off, steps
 * that already happened are no-ops.
 */
class ExecuteZaakDestruction implements ShouldQueue
{
    use Batchable, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private DestructionListItem $item) {}

    /**
     * Execute the job.
     */
    public function handle(ZaakDestructionService $service): void
    {
        $item = $this->item->refresh();

        if (in_array($item->status, [DestructionItemStatus::Deleted, DestructionItemStatus::Skipped])) {
            return;
        }

        $item->update(['status' => DestructionItemStatus::Processing, 'failure_reason' => null]);

        try {
            /** @var ?Zaak $zaak */
            $zaak = $item->zaak()->withTrashed()->first();

            // Revalidate against fresh OpenZaak data right before destroying;
            // the archiefactiedatum may have changed since the list was made.
            $ozZaak = $service->fetchZaak($item->zgw_zaak_url);

            if ($ozZaak !== null && ! $service->isEligibleForDestruction($ozZaak)) {
                $item->update([
                    'status' => DestructionItemStatus::Skipped,
                    'failure_reason' => 'Zaak is niet langer vernietigbaar volgens OpenZaak',
                ]);

                return;
            }

            if ($ozZaak !== null) {
                $result = $service->destroy($item->zgw_zaak_url);

                if ($result['skipped_documents'] !== []) {
                    Log::info("Destruction of zaak [{$item->zaaknummer}] kept shared documents: ".implode(', ', $result['skipped_documents']));
                }
            }

            // Eventloket's own data (threads, messages, the form submission
            // object, the local zaak row) is deliberately left alone here. The
            // zaak delete above makes OpenZaak fire a destroy notification,
            // which ZaakDestroyNotificationReceived acts on. That is the same
            // route a municipality's own instance takes, so both sides are
            // cleaned up by one code path and recorded once.
            //
            // The local row therefore has to survive this job: its
            // zgw_zaak_url is the only thing the notification can be matched
            // on.
            $item->update([
                'status' => DestructionItemStatus::Deleted,
                'destroyed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // Do not rethrow: other items in the batch must keep going.
            report($exception);

            Log::error("Destruction of zaak [{$item->zaaknummer}] failed: ".$exception->getMessage());

            $item->update([
                'status' => DestructionItemStatus::Failed,
                // The reason is shown to the coordinator and copied into the
                // destruction report, so it stays generic; the exception is in
                // the application log for whoever has to fix it.
                'failure_reason' => 'Er is een onverwachte fout opgetreden bij het vernietigen van deze zaak',
            ]);
        }
    }
}
