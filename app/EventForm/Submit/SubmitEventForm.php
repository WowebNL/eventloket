<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\EventForm\Persistence\DraftStore;
use App\EventForm\State\FormState;
use App\EventForm\Submit\Steps\CreateLocalZaak;
use App\EventForm\Submit\Steps\CreateZaakInZGW;
use App\Jobs\Submit\GenerateSubmissionPdf;
use App\Jobs\Submit\HashIdentifyingAttributes;
use App\Jobs\Submit\UploadFormBijlagenToZGW;
use App\Jobs\Zaak\AddEinddatumZGW;
use App\Jobs\Zaak\AddGeometryZGW;
use App\Jobs\Zaak\AddZaakeigenschappenZGW;
use App\Jobs\Zaak\CreateDoorkomstZaken;
use App\Jobs\Zaak\UpdateInitiatorZGW;
use App\Models\Organisation;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enige entry voor het afronden van een aanvraag. Vervangt OF's
 * submit-pipeline én de bestaande ProcessCreateZaak-keten in één
 * synchrone + async hybride.
 *
 * Synchroon (zodat de user direct een zaaknummer terug krijgt):
 *   1. Juiste zaaktype resolven (gemeente + aard)
 *   2. ZGW-zaak aanmaken bij OpenZaak
 *   3. Lokale `Zaak`-row aanmaken met reference_data + form_state_snapshot
 *   4. Draft leegmaken
 *   5. Audit-log-entry
 *
 * Async (queue, in dispatch-volgorde):
 *   - AddZaakeigenschappenZGW
 *   - AddEinddatumZGW
 *   - UpdateInitiatorZGW
 *   - AddGeometryZGW
 *   - CreateDoorkomstZaken  (alleen bij route-events)
 *   - GenerateSubmissionPdf  (hoge prioriteit)
 *   - SendSubmissionConfirmationEmail
 *   - HashIdentifyingAttributes  (laatste; anonimiseert BSN/KVK in state)
 */
final class SubmitEventForm
{
    public function __construct(
        private readonly ResolveZaaktype $resolveZaaktype,
        private readonly CreateZaakInZGW $createZaakInZGW,
        private readonly CreateLocalZaak $createLocalZaak,
        private readonly DraftStore $draftStore,
    ) {}

    public function execute(FormState $state, OrganiserUser $user, Organisation $organisation): Zaak
    {
        // 1. Zaaktype bepalen op basis van gemeente + aard.
        $zaaktype = $this->resolveZaaktype->forState($state);

        // 2-3. ZGW-zaak + lokale Zaak binnen één transactie. Als stap 3
        //      faalt rollen we de lokale state terug; de ZGW-zaak blijft
        //      dan wel bestaan bij OpenZaak — dat accepteren we omdat een
        //      halve ZGW-delete riskanter is dan een harmless weeszaak
        //      die behandelaars handmatig kunnen opruimen.
        $zaak = DB::transaction(function () use ($state, $zaaktype, $user, $organisation) {
            $ozZaak = $this->createZaakInZGW->execute($state, $zaaktype);

            return $this->createLocalZaak->execute(
                state: $state,
                ozZaak: $ozZaak,
                zaaktype: $zaaktype,
                user: $user,
                organisation: $organisation,
            );
        });

        // 4. Draft leegmaken zodat een volgende aanvraag met een leeg
        //    formulier start.
        $this->draftStore->clear($user, $organisation);

        // 5. Audit-log voor compliance — equivalent van OF's
        //    FORM_SUBMIT_SUCCESS_EVENT.
        Log::channel(config('logging.default'))->info('event_form_submitted', [
            'zaak_id' => $zaak->id,
            'public_id' => $zaak->public_id,
            'zgw_zaak_url' => $zaak->zgw_zaak_url,
            'zaaktype_id' => $zaaktype->id,
            'zaaktype_name' => $zaaktype->name,
            'organiser_user_id' => $user->id,
            'organisation_id' => $organisation->id,
        ]);

        // 6. Async keten dispatchen. Volgorde komt overeen met wat
        //    ProcessCreateZaak deed; PDF/email/hash zijn nieuwe
        //    nevenacties die OF ook had.
        $this->dispatchAsyncChain($zaak);

        return $zaak;
    }

    private function dispatchAsyncChain(Zaak $zaak): void
    {
        Bus::chain([
            new AddZaakeigenschappenZGW($zaak),
            new AddEinddatumZGW($zaak),
            new UpdateInitiatorZGW($zaak),
            new AddGeometryZGW($zaak),
            new CreateDoorkomstZaken($zaak),
        ])->dispatch();

        // PDF + email + privacy-hash komen als losse (niet-ketende) jobs
        // zodat ze onafhankelijk kunnen falen/retrien. De Mailable
        // hangt aan de PDF-job (die dispatched 'm nadat de PDF klaar is).
        // De PDF-job dispatcht óók UploadSubmissionPdfToZGW, dus daar
        // hoeven we hier niets voor te regelen.
        GenerateSubmissionPdf::dispatch($zaak)->onQueue('high');

        // Upload alle FileUpload-bijlagen die de organisator heeft
        // toegevoegd als zaakinformatieobject naar OpenZaak. Apart van
        // de PDF-job omdat hier geen volgorde-afhankelijkheid is — de
        // bijlagen liggen al op disk vanaf het moment dat de organisator
        // ze uploadde tijdens het invullen.
        UploadFormBijlagenToZGW::dispatch($zaak);

        HashIdentifyingAttributes::dispatch($zaak);
    }
}
