<?php

namespace App\Jobs;

use App\Enums\DocumentVertrouwelijkheden;
use App\Models\Zaak;
use App\Notifications\NewZaakDocument;
use App\ValueObjects\OpenNotification;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Woweb\Openzaak\Openzaak;

class DocumentNotificationReceived implements ShouldQueue
{
    use Queueable;

    private OpenZaak $openzaak;

    /**
     * Create a new job instance.
     */
    public function __construct(private OpenNotification $notification, private bool $isNew)
    {
        $this->openzaak = new OpenZaak;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $informatieobject = new Informatieobject(...$this->openzaak->get($this->notification->hoofdObject)->toArray());

        if ($this->isNew) {
            // ignore documents received while creating the zaak
            if ($informatieobject->auteur == config('services.open_forms.auteur_name')) {
                // Document created by the applicant in open forms, ignore
                return;
            }

            $this->notifyUsers($informatieobject, true);
        } else {
            $this->notifyUsers($informatieobject, false);
        }
    }

    private function notifyUsers(Informatieobject $informatieobject, bool $isNew = true)
    {
        $zaakinformatieObject = $this->openzaak->zaken()->zaakinformatieobjecten()->getAll([
            'informatieobject' => $this->notification->hoofdObject,
        ])->first();

        if (Arr::has($zaakinformatieObject, 'zaak') && $zaakUrl = Arr::get($zaakinformatieObject, 'zaak')) {
            $zaak = Zaak::where('zgw_zaak_url', $zaakUrl)->first();
            if ($zaak) {
                $users = $zaak->relatedUsers();
                foreach ($users as $user) {
                    /** @var \App\Enums\Role $role */
                    $role = $user->role;
                    if (
                        in_array($informatieobject->vertrouwelijkheidaanduiding, DocumentVertrouwelijkheden::fromUserRole($role)) // user has acces to document
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
}
