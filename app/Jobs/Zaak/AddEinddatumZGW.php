<?php

namespace App\Jobs\Zaak;

use App\ValueObjects\OzZaak;
use App\ValueObjects\OzZaaktype;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Openzaak\Openzaak;

class AddEinddatumZGW implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private string $zaakUrlZGW) {}

    /**
     * Execute the job.
     */
    public function handle(Openzaak $openzaak): void
    {
        $zaak = new OzZaak(...$openzaak->get($this->zaakUrlZGW)->toArray());

        if (! $zaak->uiterlijkeEinddatumAfdoening && ! $zaak->einddatumGepland) {
            $zaaktype = new OzZaaktype(...$openzaak->get($zaak->zaaktype)->toArray());
            $uiterlijkeEinddatumAfdoeningInterval = new CarbonInterval($zaaktype->doorlooptijd);
            $einddatumGeplandInterval = new CarbonInterval($zaaktype->servicenorm);

            $resp = $openzaak->zaken()->zaken()->patch($zaak->uuid, [
                'einddatumGepland' => Carbon::parse($zaak->startdatum)->add($einddatumGeplandInterval)->format('Y-m-d'),
                'uiterlijkeEinddatumAfdoening' => Carbon::parse($zaak->startdatum)->add($uiterlijkeEinddatumAfdoeningInterval)->format('Y-m-d'),
            ]);
        }

    }
}
