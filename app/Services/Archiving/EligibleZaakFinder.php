<?php

namespace App\Services\Archiving;

use App\Enums\DestructionListStatus;
use App\Models\Archiving\DestructionListItem;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\Services\Zgw\ZgwConnectionResolver;
use App\ValueObjects\Archiving\EligibleZaak;
use Illuminate\Support\Collection;
use Woweb\Zgw\Data\Generated\Zaken\Enums\Archiefnominatie;
use Woweb\Zgw\Facades\Zgw;

/**
 * Finds zaken of a municipality that may be destroyed according to OpenZaak:
 * archiefnominatie "vernietigen" and an archiefactiedatum that has passed.
 * Only zaken that exist in Eventloket (matched on zgw_zaak_url) are returned,
 * so imported zaken without a ZGW registration are never eligible.
 *
 * Restricted to zaaktypen that live on our own OpenZaak. A municipality running
 * its own ZGW instance archives and destroys there, in its own system and under
 * its own selectielijst; Eventloket never destroys on somebody else's instance.
 * The filter is per zaaktype rather than per municipality on purpose: such a
 * municipality can still have zaaktypen that fall back to main, and the zaken on
 * those do belong here.
 */
class EligibleZaakFinder
{
    /**
     * @return Collection<int, EligibleZaak>
     */
    public function find(Municipality $municipality): Collection
    {
        $zgwZakenByUrl = collect();

        /** @var Zaaktype $zaaktype */
        foreach ($municipality->zaaktypen as $zaaktype) {
            if (! $zaaktype->zgw_zaaktype_url) {
                continue;
            }

            if ($zaaktype->zgwConnectionName() !== ZgwConnectionResolver::DEFAULT_CONNECTION) {
                continue;
            }

            $results = Zgw::connection(ZgwConnectionResolver::DEFAULT_CONNECTION)
                ->zaken()
                ->zaken()
                ->index([
                    'zaaktype' => $zaaktype->zgw_zaaktype_url,
                    'archiefnominatie' => Archiefnominatie::Vernietigen->value,
                    // Strictly before today: a zaak may be destroyed once its
                    // archiefactiedatum has passed, not on the day itself.
                    'archiefactiedatum__lt' => now()->startOfDay()->format('Y-m-d'),
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
