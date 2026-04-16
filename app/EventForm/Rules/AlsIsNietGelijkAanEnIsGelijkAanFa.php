<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 29ff6bf6-c3fb-42e6-b523-d5478d203b85
 *
 * @openforms-rule-description Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{eventloketPrefillLoaded}} is gelijk aan fa…
 */
final class AlsIsNietGelijkAanEnIsGelijkAanFa implements Rule
{
    public function identifier(): string
    {
        return '29ff6bf6-c3fb-42e6-b523-d5478d203b85';
    }

    public function triggerStepUuids(): array
    {
        return ['c3c17c65-0cf1-4a79-a348-75eab01f46ec'];
    }

    public function effectStepUuids(): array
    {
        return [];
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('watIsDeNaamVanHetEvenementVergunning', $s->get('eventloketPrefill.naam-van-het-evenement.watIsDeNaamVanHetEvenementVergunning'));
        $s->setVariable('geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning', $s->get('eventloketPrefill.naam-van-het-evenement.geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning'));
        $s->setVariable('soortEvenement', $s->get('eventloketPrefill.naam-van-het-evenement.soortEvenement'));
        $s->setVariable('gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen', $s->get('eventloketPrefill.naam-van-het-evenement.gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen'));
        $s->setVariable('routesOpKaart', $s->get('eventloketPrefill.locatie-van-het-evenement-2.route.routesOpKaart'));
        $s->setVariable('naamVanDeRoute', $s->get('eventloketPrefill.locatie-van-het-evenement-2.route.naamVanDeRoute'));
        $s->setVariable('gpxBestandVanDeRoute', $s->get('eventloketPrefill.locatie-van-het-evenement-2.route.gpxBestandVanDeRoute'));
        $s->setVariable('watVoorEvenementGaatPlaatsvindenOpDeRoute1', $s->get('eventloketPrefill.locatie-van-het-evenement-2.route.watVoorEvenementGaatPlaatsvindenOpDeRoute1'));
        $s->setVariable('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan', $s->get('eventloketPrefill.locatie-van-het-evenement-2.route.komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan'));
        $s->setVariable('locatieSOpKaart', $s->get('eventloketPrefill.locatie-van-het-evenement-2.locatieSOpKaart'));
        $s->setVariable('adresVanDeGebouwEn', $s->get('eventloketPrefill.locatie-van-het-evenement-2.adresVanDeGebouwEn'));
        $s->setVariable('waarVindtHetEvenementPlaats', $s->get('eventloketPrefill.locatie-van-het-evenement-2.waarVindtHetEvenementPlaats'));
        $s->setVariable('eventloketPrefillLoaded', true);
    }
}
