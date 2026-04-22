<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;
use App\EventForm\Transpiler\JsTruthy;

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
        return (bool) (JsTruthy::of($s->get('watIsDeAantrekkingskrachtVanHetEvenement')) && JsTruthy::of($s->get('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep')) && JsTruthy::of($s->get('isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid')) && JsTruthy::of($s->get('isEenDeelVanDeDoelgroepVerminderdZelfredzaam')) && JsTruthy::of($s->get('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten')) && JsTruthy::of($s->get('watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep')) && JsTruthy::of($s->get('isErSprakeVanOvernachten')) && JsTruthy::of($s->get('isErGebruikVanAlcoholEnDrugs')) && JsTruthy::of($s->get('watIsHetAantalGelijktijdigAanwezigPersonen')) && JsTruthy::of($s->get('inWelkSeizoenVindtHetEvenementPlaats')) && JsTruthy::of($s->get('inWelkeLocatieVindtHetEvenementPlaats')) && JsTruthy::of($s->get('opWelkSoortOndergrondVindtHetEvenementPlaats')) && JsTruthy::of($s->get('watIsDeTijdsduurVanHetEvenement')) && JsTruthy::of($s->get('welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing')));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('risicoClassificatie', (((((float) $s->get('watIsDeAantrekkingskrachtVanHetEvenement')) + ((float) $s->get('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep')) + ((float) $s->get('isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid')) + ((float) $s->get('isEenDeelVanDeDoelgroepVerminderdZelfredzaam')) + ((float) $s->get('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten')) + ((float) $s->get('watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep')) + ((float) $s->get('isErSprakeVanOvernachten')) + ((float) $s->get('isErGebruikVanAlcoholEnDrugs')) + ((float) $s->get('watIsHetAantalGelijktijdigAanwezigPersonen')) + ((float) $s->get('inWelkSeizoenVindtHetEvenementPlaats')) + ((float) $s->get('inWelkeLocatieVindtHetEvenementPlaats')) + ((float) $s->get('opWelkSoortOndergrondVindtHetEvenementPlaats')) + ((float) $s->get('watIsDeTijdsduurVanHetEvenement')) + ((float) $s->get('welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing'))) <= 6) ? 'A' : (((((float) $s->get('watIsDeAantrekkingskrachtVanHetEvenement')) + ((float) $s->get('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep')) + ((float) $s->get('isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid')) + ((float) $s->get('isEenDeelVanDeDoelgroepVerminderdZelfredzaam')) + ((float) $s->get('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten')) + ((float) $s->get('watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep')) + ((float) $s->get('isErSprakeVanOvernachten')) + ((float) $s->get('isErGebruikVanAlcoholEnDrugs')) + ((float) $s->get('watIsHetAantalGelijktijdigAanwezigPersonen')) + ((float) $s->get('inWelkSeizoenVindtHetEvenementPlaats')) + ((float) $s->get('inWelkeLocatieVindtHetEvenementPlaats')) + ((float) $s->get('opWelkSoortOndergrondVindtHetEvenementPlaats')) + ((float) $s->get('watIsDeTijdsduurVanHetEvenement')) + ((float) $s->get('welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing'))) <= 9) ? 'B' : 'C')));
    }
}
