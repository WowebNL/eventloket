<?php

namespace App\Jobs\Archiving;

use App\Enums\DestructionItemStatus;
use App\Models\Archiving\DestructionListItem;
use App\Models\Message;
use App\Models\Thread;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use App\Models\Zaak;
use App\Services\Archiving\ZaakDestructionService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Destroys a single zaak: first in OpenZaak (besluiten, documents, zaak),
 * then all local Eventloket data (threads, messages, notifications, activity
 * log and the zaak itself). Idempotent: a retry after a partial failure
 * resumes where it left off, steps that already happened are no-ops.
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

            if ($zaak?->data_object_url) {
                $service->deleteDataObject($zaak->data_object_url);
            }

            if ($zaak) {
                $this->destroyLocalData($zaak);
            }

            $item->update([
                'status' => DestructionItemStatus::Deleted,
                'destroyed_at' => now(),
                'zaak_id' => null,
            ]);
        } catch (Throwable $exception) {
            // Do not rethrow: other items in the batch must keep going.
            report($exception);

            $item->update([
                'status' => DestructionItemStatus::Failed,
                'failure_reason' => $exception->getMessage(),
            ]);
        }
    }

    private function destroyLocalData(Zaak $zaak): void
    {
        $zaak->clearZgwCache();

        DB::transaction(function () use ($zaak) {
            $threadIds = Thread::where('zaak_id', $zaak->id)->toBase()->pluck('id');
            $messageIds = Message::whereIn('thread_id', $threadIds)->toBase()->pluck('id');

            Activity::query()
                ->where(fn ($query) => $query
                    ->where(fn ($subQuery) => $subQuery->where('subject_type', Zaak::class)->where('subject_id', $zaak->id))
                    ->orWhere(fn ($subQuery) => $subQuery->whereIn('subject_type', [Thread::class, AdviceThread::class, OrganiserThread::class])->whereIn('subject_id', $threadIds))
                    ->orWhere(fn ($subQuery) => $subQuery->where('subject_type', Message::class)->whereIn('subject_id', $messageIds)))
                ->delete();

            $this->deleteNotificationsReferencing($zaak);

            // threads.zaak_id has no cascade, so threads go first; messages,
            // unread_messages and thread_user cascade from threads/messages.
            Thread::whereIn('id', $threadIds)->delete();

            // Without this the delete itself would leave a new activity log
            // entry referencing the destroyed zaak.
            $zaak->disableLogging();
            $zaak->forceDelete();
        });
    }

    private function deleteNotificationsReferencing(Zaak $zaak): void
    {
        $dataAsText = match (DB::connection()->getDriverName()) {
            'pgsql' => 'data::text',
            'mysql', 'mariadb' => 'CAST(data AS CHAR)',
            default => 'CAST(data AS TEXT)',
        };

        DB::table('notifications')
            ->whereRaw("{$dataAsText} LIKE ?", ['%'.$zaak->id.'%'])
            ->delete();
    }
}
