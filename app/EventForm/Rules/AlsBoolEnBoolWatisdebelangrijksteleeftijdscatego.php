<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 55ce8acd-f972-417d-8920-64c8b0744e14
 *
 * @openforms-rule-description Als bool({{watIsDeAantrekkingskrachtVanHetEvenement}})en bool({{watIsDeBelangrijksteLeeftijdscatego…
 */
final class AlsBoolEnBoolWatisdebelangrijksteleeftijdscatego implements Rule
{
    public function identifier(): string
    {
        return '55ce8acd-f972-417d-8920-64c8b0744e14';
    }

    public function triggerStepUuids(): array
    {
        return ['c75cc256-6729-4684-9f9b-ede6265b3e72'];
    }

    public function effectStepUuids(): array
    {
        return [];
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((bool) $s->get('watIsDeAantrekkingskrachtVanHetEvenement') && (bool) $s->get('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep') && (bool) $s->get('isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid') && (bool) $s->get('isEenDeelVanDeDoelgroepVerminderdZelfredzaam') && (bool) $s->get('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten') && (bool) $s->get('watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep') && (bool) $s->get('isErSprakeVanOvernachten') && (bool) $s->get('isErGebruikVanAlcoholEnDrugs') && (bool) $s->get('watIsHetAantalGelijktijdigAanwezigPersonen') && (bool) $s->get('inWelkSeizoenVindtHetEvenementPlaats') && (bool) $s->get('inWelkeLocatieVindtHetEvenementPlaats') && (bool) $s->get('opWelkSoortOndergrondVindtHetEvenementPlaats') && (bool) $s->get('watIsDeTijdsduurVanHetEvenement') && (bool) $s->get('welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing'));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('risicoClassificatie', (((((float) $s->get('watIsDeAantrekkingskrachtVanHetEvenement')) + ((float) $s->get('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep')) + ((float) $s->get('isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid')) + ((float) $s->get('isEenDeelVanDeDoelgroepVerminderdZelfredzaam')) + ((float) $s->get('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten')) + ((float) $s->get('watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep')) + ((float) $s->get('isErSprakeVanOvernachten')) + ((float) $s->get('isErGebruikVanAlcoholEnDrugs')) + ((float) $s->get('watIsHetAantalGelijktijdigAanwezigPersonen')) + ((float) $s->get('inWelkSeizoenVindtHetEvenementPlaats')) + ((float) $s->get('inWelkeLocatieVindtHetEvenementPlaats')) + ((float) $s->get('opWelkSoortOndergrondVindtHetEvenementPlaats')) + ((float) $s->get('watIsDeTijdsduurVanHetEvenement')) + ((float) $s->get('welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing'))) <= 6) ? 'A' : ((((float) $s->get('watIsDeAantrekkingskrachtVanHetEvenement')) + ((float) $s->get('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep')) + ((float) $s->get('isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid')) + ((float) $s->get('isEenDeelVanDeDoelgroepVerminderdZelfredzaam')) + ((float) $s->get('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten')) + ((float) $s->get('watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep')) + ((float) $s->get('isErSprakeVanOvernachten')) + ((float) $s->get('isErGebruikVanAlcoholEnDrugs')) + ((float) $s->get('watIsHetAantalGelijktijdigAanwezigPersonen')) + ((float) $s->get('inWelkSeizoenVindtHetEvenementPlaats')) + ((float) $s->get('inWelkeLocatieVindtHetEvenementPlaats')) + ((float) $s->get('opWelkSoortOndergrondVindtHetEvenementPlaats')) + ((float) $s->get('watIsDeTijdsduurVanHetEvenement')) + ((float) $s->get('welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing'))) <= 9)));
    }
}
