<?php

declare(strict_types=1);

namespace App\EventForm\Reporting;

use App\EventForm\State\FormState;
use App\EventForm\Submit\DetermineAanvraagType;

/**
 * Centrale berekening van de "onderdelen van uw aanvraag"-lijst.
 *
 * Wordt op twee plekken gebruikt:
 *   1. `TypeAanvraagStep` (UI) — toont de lijst aan de organisator op
 *      de laatste stap voor indienen.
 *   2. `SubmissionReport::buildTypeAanvraagEntry` — neemt 'm op in de
 *      Samenvatting + PDF.
 *
 * Eén plek voor de logica voorkomt dat UI en PDF/samenvatting uit elkaar
 * gaan lopen wanneer er een nieuw onderdeel bijkomt.
 */
final class TypeAanvraagOnderdelen
{
    /**
     * @return list<string>
     */
    public static function buildList(FormState $state): array
    {
        // Without a choice for `waarvoorWiltUEventloketGebruiken` there is
        // nothing to say about the type of application yet, so we show no
        // (empty) section.
        if (((string) ($state->get('waarvoorWiltUEventloketGebruiken') ?? '')) === '') {
            return [];
        }

        // Derive the main item from the same canonical determination that picks
        // the zaaktype (`ResolveZaaktype`). This method used to hold its own
        // legacy-only copy of that logic (the road-closure question alone),
        // which made municipalities on the new ReportQuestion system (such as
        // Heerlen) see "Evenementenvergunning" for a melding.
        $role = app(DetermineAanvraagType::class)->forState($state);

        // The label text matches the zaaktype name prefix per role
        // (Vergunning => "Evenementenvergunning", Melding => "Melding",
        // Vooraankondiging => "Vooraankondiging").
        return [$role->namePrefix()];
    }

    /**
     * Items die de aanvrager zelf nog moet regelen (niet via Eventloket).
     *
     * @return list<string>
     */
    public static function buildZelfTeRegelenList(FormState $state): array
    {
        $items = [];

        if ($state->get('alcoholvergunning') === 'Ja') {
            $items[] = 'Ontheffing Alcoholwet, indien een externe organisatie verantwoordelijk is';
        }
        if ($state->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A3') === true) {
            $items[] = 'Gebruiksmelding brandveilig gebruik en basishulpverlening overige plaatsen';
        }
        if (
            $state->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A48') === true
            || $state->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A49') === true
        ) {
            $items[] = 'Ontheffing plaatsen object of parkeren grote voertuigen op de openbare weg';
        }
        if ($state->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A4') === true) {
            $items[] = 'Vergunning kansspelen';
        }
        // if ($state->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A51') === true) {
        //     $items[] = 'Aanstellingsbesluit verkeersregelaars';
        // }

        return $items;
    }
}
