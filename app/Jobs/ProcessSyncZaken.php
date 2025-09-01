<?php

namespace App\Jobs;

use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Openzaak\Openzaak;

class ProcessSyncZaken implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $openzaak = new Openzaak;
        $zaken = $openzaak->zaken()->zaken()->getAll();

        // TODO: Ik wacht nog op antwoord van Michel of het mogelijk is om meer zaaktype data te includen

        foreach ($zaken as $zaak) {

            $zaaktypeId = basename($zaak['zaaktype']);

            // TODO: Aanmaken ipv fail
            $zaaktype = Zaaktype::where('id', $zaaktypeId)->firstOrFail();

            Zaak::create([
                'id' => $zaak['uuid'],
                'public_id' => $zaak['identificatie'],
                'zaaktype_id' => $zaaktype->id,
                'organisation_id' => $zaak['verantwoordelijkeOrganisatie'],
                'name' => $zaak['omschrijving'],
            ]);
        }
    }
}
