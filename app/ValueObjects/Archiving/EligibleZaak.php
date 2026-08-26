<?php

namespace App\ValueObjects\Archiving;

use App\Models\Zaak;
use Carbon\Carbon;
use Illuminate\Support\Arr;

/**
 * A zaak that is eligible for destruction according to OpenZaak
 * (archiefnominatie "vernietigen" and an expired archiefactiedatum),
 * together with the grounds (selectielijst) it is based on.
 */
class EligibleZaak
{
    public function __construct(
        public readonly Zaak $zaak,
        public readonly ?string $archiefnominatie,
        public readonly ?Carbon $archiefactiedatum,
        public readonly ?string $archiefstatus,
        public readonly ?string $selectielijstklasse,
        public readonly ?string $selectielijstCategorie,
        public readonly ?string $bewaartermijn,
        public readonly ?string $brondatumArchiefprocedure,
    ) {}

    public static function fromZgwData(Zaak $zaak, array $data): self
    {
        $resultaattype = Arr::get($data, '_expand.resultaat._expand.resultaattype', []);

        return new self(
            zaak: $zaak,
            archiefnominatie: Arr::get($data, 'archiefnominatie'),
            archiefactiedatum: Arr::get($data, 'archiefactiedatum') ? Carbon::parse($data['archiefactiedatum']) : null,
            archiefstatus: Arr::get($data, 'archiefstatus'),
            selectielijstklasse: Arr::get($resultaattype, 'selectielijstklasse'),
            selectielijstCategorie: Arr::get($resultaattype, 'omschrijving'),
            bewaartermijn: Arr::get($resultaattype, 'archiefactietermijn'),
            brondatumArchiefprocedure: Arr::get($resultaattype, 'brondatumArchiefprocedure.afleidingswijze'),
        );
    }

    /**
     * Snapshot attributes for a DestructionListItem.
     */
    public function toItemAttributes(): array
    {
        return [
            'zaak_id' => $this->zaak->id,
            'zgw_zaak_url' => $this->zaak->zgw_zaak_url,
            'zaaknummer' => $this->zaak->public_id,
            'zaaktype_naam' => $this->zaak->zaaktype?->name,
            'naam_evenement' => $this->zaak->reference_data->naam_evenement ?? null,
            'archiefnominatie' => $this->archiefnominatie,
            'archiefactiedatum' => $this->archiefactiedatum,
            'archiefstatus' => $this->archiefstatus,
            'selectielijstklasse' => $this->selectielijstklasse,
            'selectielijst_categorie' => $this->selectielijstCategorie,
            'bewaartermijn' => $this->bewaartermijn,
            'brondatum_archiefprocedure' => $this->brondatumArchiefprocedure,
        ];
    }
}
