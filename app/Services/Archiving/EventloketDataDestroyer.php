<?php

namespace App\Services\Archiving;

use App\Models\Archiving\ZaakDestructionLog;
use App\Models\Message;
use App\Models\Thread;
use App\Models\Threads\AdviceThread;
use App\Models\Threads\OrganiserThread;
use App\Models\Zaak;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Removes everything Eventloket itself holds about a zaak, once that zaak is
 * gone from the zaaksysteem.
 *
 * This is the other half of the destruction: the zaakdata lives in ZGW and is
 * destroyed there (by our own archive module on the main connection, or by the
 * municipality in its own instance), while threads, messages, notifications,
 * the activity log, the form submission object and the local zaak row are ours
 * and are destroyed here. Both halves are triggered by the same signal, a ZGW
 * "destroy" notification, so an external instance and our own behave alike.
 *
 * Writes exactly one {@see ZaakDestructionLog} row per zaak, which is the only
 * evidence left afterwards and what the nightly report is built from.
 *
 * Idempotent: a zaak that has already been destroyed is a no-op, so a replayed
 * notification costs nothing.
 */
class EventloketDataDestroyer
{
    public function __construct(private readonly ZaakDestructionService $zgw) {}

    /**
     * Destroy Eventloket's data about this zaak and record that it happened.
     *
     * Returns the log row, or null when there was nothing left to do.
     */
    public function destroy(Zaak $zaak): ?ZaakDestructionLog
    {
        $zaakUrl = (string) $zaak->zgw_zaak_url;

        if ($zaakUrl === '') {
            return null;
        }

        // Snapshot before anything is removed: afterwards there is nothing to
        // read these from.
        $entry = [
            'municipality_id' => $zaak->zaaktype?->municipality_id,
            'zgw_connection' => $zaak->zgwConnectionName(),
            'zgw_zaak_url' => $zaakUrl,
            'zaaknummer' => $zaak->public_id,
            'zaaktype_naam' => $zaak->zaaktype?->name,
            'destroyed_at' => now(),
        ];

        $this->deleteDataObject($zaak);
        $this->deleteLocalData($zaak);

        // firstOrCreate on the unique zgw_zaak_url: a replayed notification for
        // a zaak we already cleaned up must not add a second row, which would
        // put the same zaak on two reports.
        return ZaakDestructionLog::firstOrCreate(
            ['zgw_zaak_url' => $zaakUrl],
            $entry,
        );
    }

    /**
     * The form submission object in the Objects API. Eventloket's own store, so
     * it belongs to this side of the destruction rather than to the zaakdata.
     *
     * A failure here must not strand the rest: the local data is the larger
     * exposure, and the object can be cleaned up separately.
     */
    private function deleteDataObject(Zaak $zaak): void
    {
        if (! $zaak->data_object_url) {
            return;
        }

        try {
            $this->zgw->deleteDataObject($zaak->data_object_url);
        } catch (Throwable $exception) {
            report($exception);

            Log::error("Destroying the form submission object of zaak [{$zaak->public_id}] failed: ".$exception->getMessage());
        }
    }

    private function deleteLocalData(Zaak $zaak): void
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
