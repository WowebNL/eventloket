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
        // Zonder een keuze bij `waarvoorWiltUEventloketGebruiken` valt er nog
        // niets te zeggen over het type aanvraag; dan tonen we geen (lege)
        // sectie.
        if (((string) ($state->get('waarvoorWiltUEventloketGebruiken') ?? '')) === '') {
            return [];
        }

        // Leid het hoofdonderdeel af uit dezelfde canonieke bepaling die ook
        // het zaaktype kiest (`ResolveZaaktype`) en de samenvatting/PDF stuurt
        // (`SubmissionReport::isMelding`). Voorheen had deze methode een eigen,
        // legacy-only kopie van die logica (alleen de wegafsluiting-vraag),
        // waardoor gemeenten op het nieuwe ReportQuestion-systeem (zoals
        // Heerlen) ten onrechte "Evenementenvergunning" zagen bij een melding.
        $role = app(DetermineAanvraagType::class)->forState($state);

        // De label-tekst is gelijk aan de zaaktype-naamprefix per rol
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
