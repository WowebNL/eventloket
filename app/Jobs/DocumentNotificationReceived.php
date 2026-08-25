<?php

namespace App\Jobs;

use App\Enums\Role;
use App\Models\Zaak;
use App\Notifications\NewZaakDocument;
use App\Services\Zgw\NotificationResourceReader;
use App\Services\Zgw\SubmissionDocumentDetector;
use App\Services\Zgw\ZgwConnectionConfig;
use App\ValueObjects\OpenNotification;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Woweb\Zgw\Api\Endpoints\DirectEndpoint;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Facades\Zgw;

class DocumentNotificationReceived implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private OpenNotification $notification, private bool $isNew) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Which connection this document belongs to is resolved here (never
        // serialized), so the read runs against the instance that owns it even
        // when several connections are configured against the same host.
        $reader = app(NotificationResourceReader::class);
        $resource = $reader->resolve($this->notification);

        if ($resource === null) {
            // Another organisation's document on a shared instance.
            return;
        }

        $connection = Zgw::connection($resource->connection);

        $informatieobject = new Informatieobject(...$reader->read($resource, $this->notification));

        if ($this->isNew) {
            // ignore documents received while creating the zaak
            if ($informatieobject->auteur == config('services.open_forms.auteur_name')) {
                // Document created by the applicant in open forms, ignore
                return;
            }

            $this->notifyUsers($connection, $informatieobject, true);
        } else {
            $this->notifyUsers($connection, $informatieobject, false);
        }
    }

    private function notifyUsers(ZgwConnection $connection, Informatieobject $informatieobject, bool $isNew = true)
    {
        $zaakinformatieObject = $connection->zaken()->zaakinformatieobjecten()->index([
            'informatieobject' => $this->notification->hoofdObject,
        ])->first();

        if (Arr::has($zaakinformatieObject, 'zaak') && $zaakUrl = Arr::get($zaakinformatieObject, 'zaak')) {
            $zaak = Zaak::where('zgw_zaak_url', $zaakUrl)->first();
            if ($zaak) {
                // Version 1 of a submission document (the application form PDF
                // and its attachments) triggers no notification: the organiser
                // already receives the confirmation mail. Version 2 and up
                // (isNew=false) does trigger one.
                if ($isNew && SubmissionDocumentDetector::isSubmissionDocument($informatieobject, $zaak)) {
                    $zaak->clearZgwCache();

                    return;
                }

                // Only notify for finalised documents (no concepts), and for a
                // besluitdocument only once the besluit's verzenddatum is reached.
                if (! $informatieobject->isVastgesteld() || ! $this->besluitVerzenddatumReached($connection, $informatieobject)) {
                    $zaak->clearZgwCache();

                    return;
                }

                $users = $zaak->relatedUsers();
                foreach ($users as $user) {
                    /** @var Role $role */
                    $role = $user->role;
                    if (
                        in_array($informatieobject->vertrouwelijkheidaanduiding, ZgwConnectionConfig::documentVisibilityForRole($zaak->zgwConnectionName(), $role)) // user has acces to document
                        && $user->name != $informatieobject->auteur // not own update
                    ) {
                        // Notify user about new document
                        $user->notify(new NewZaakDocument($zaak, $informatieobject->titel, $isNew));
                    }
                }

                $zaak->clearZgwCache();
            }
        } else {
            Log::warning("Received document notification for informatieobject {$this->notification->hoofdObject} which is not linked to a zaak.");
        }
    }

    /**
     * Whether a document may be notified about with respect to a besluit's
     * verzenddatum. Returns true for ordinary documents (not linked to a
     * besluit). For a besluitdocument it returns true only once the besluit's
     * verzenddatum has been reached. Any lookup failure defaults to true so a
     * besluiten-API hiccup never silently swallows a normal notification.
     */
    private function besluitVerzenddatumReached(ZgwConnection $connection, Informatieobject $informatieobject): bool
    {
        try {
            $link = collect($connection->besluiten()->besluitinformatieobjecten()->index([
                'informatieobject' => $this->notification->hoofdObject,
            ]))->first();

            if (! is_array($link) || empty($link['besluit'])) {
                return true;
            }

            $besluit = (new DirectEndpoint($connection))->getByUrl($link['besluit']);
            $verzenddatum = $besluit['verzenddatum'] ?? null;

            if (empty($verzenddatum)) {
                return false;
            }

            return Carbon::parse($verzenddatum, 'Europe/Amsterdam')
                ->startOfDay()
                ->lessThanOrEqualTo(Carbon::now('Europe/Amsterdam')->startOfDay());
        } catch (\Throwable $e) {
            Log::warning('Could not determine besluit verzenddatum for document notification: '.$e->getMessage());

            return true;
        }
    }
}
