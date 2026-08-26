<?php

namespace App\Services\Archiving;

use App\Enums\DestructionListStatus;
use App\Models\Archiving\DestructionListItem;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\Archiving\EligibleZaak;
use Illuminate\Support\Collection;
use Woweb\Openzaak\Openzaak;

/**
 * Finds zaken of a municipality that may be destroyed according to OpenZaak:
 * archiefnominatie "vernietigen" and an archiefactiedatum that has passed.
 * Only zaken that exist in Eventloket (matched on zgw_zaak_url) are returned,
 * so imported zaken without a ZGW registration are never eligible.
 */
class EligibleZaakFinder
{
    /**
     * @return Collection<int, EligibleZaak>
     */
    public function find(Municipality $municipality): Collection
    {
        $openzaak = new Openzaak;

        $zgwZakenByUrl = collect();

        /** @var Zaaktype $zaaktype */
        foreach ($municipality->zaaktypen as $zaaktype) {
            if (! $zaaktype->zgw_zaaktype_url) {
                continue;
            }

            $results = $openzaak->zaken()->zaken()->getAll([
                'zaaktype' => $zaaktype->zgw_zaaktype_url,
                'archiefnominatie' => 'vernietigen',
                'archiefactiedatum__lt' => now()->addDay()->format('Y-m-d'),
                'expand' => 'resultaat,resultaat.resultaattype',
            ]);

            foreach ($results as $data) {
                $zgwZakenByUrl->put($data['url'], $data);
            }
        }

        if ($zgwZakenByUrl->isEmpty()) {
            return collect();
        }

        $zaken = Zaak::withTrashed()
            ->whereIn('zgw_zaak_url', $zgwZakenByUrl->keys())
            ->with('zaaktype')
            ->get();

        // A zaak that is already on a destruction list that has not been fully
        // destroyed yet cannot be added to another list.
        $alreadyListedZaakIds = DestructionListItem::query()
            ->whereIn('zaak_id', $zaken->pluck('id'))
            ->whereHas('destructionList', fn ($query) => $query->whereNot('status', DestructionListStatus::Deleted))
            ->pluck('zaak_id');

        return $zaken
            ->reject(fn (Zaak $zaak) => $alreadyListedZaakIds->contains($zaak->id))
            ->map(fn (Zaak $zaak) => EligibleZaak::fromZgwData($zaak, $zgwZakenByUrl->get($zaak->zgw_zaak_url)))
            ->values();
    }
}
