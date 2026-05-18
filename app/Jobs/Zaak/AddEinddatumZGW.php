<?php

declare(strict_types=1);

namespace App\Jobs\Zaak;

use App\Models\Zaak;
use App\ValueObjects\OzZaak;
use App\ValueObjects\OzZaaktype;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Woweb\Openzaak\Openzaak;

/**
 * Vult `einddatumGepland` + `uiterlijkeEinddatumAfdoening` op de ZGW-zaak
 * op basis van de `servicenorm` en `doorlooptijd` van het zaaktype.
 * Geen FormState nodig — puur een berekening op bestaande ZGW-data.
 */
class AddEinddatumZGW implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Zaak $zaak) {}

    public function handle(Openzaak $openzaak): void
    {
        if (! $this->zaak->zgw_zaak_url) {
            return;
        }

        $ozZaak = new OzZaak(...$openzaak->get($this->zaak->zgw_zaak_url)->toArray());

        if ($ozZaak->uiterlijkeEinddatumAfdoening || $ozZaak->einddatumGepland) {
            return;
        }

        $zaaktype = new OzZaaktype(...$openzaak->get($ozZaak->zaaktype)->toArray());
        $doorlooptijd = new CarbonInterval($zaaktype->doorlooptijd);
        $servicenorm = new CarbonInterval($zaaktype->servicenorm);

        $openzaak->zaken()->zaken()->patch($ozZaak->uuid, [
            'einddatumGepland' => Carbon::parse($ozZaak->startdatum)->add($servicenorm)->format('Y-m-d'),
            'uiterlijkeEinddatumAfdoening' => Carbon::parse($ozZaak->startdatum)->add($doorlooptijd)->format('Y-m-d'),
        ]);
    }
}
