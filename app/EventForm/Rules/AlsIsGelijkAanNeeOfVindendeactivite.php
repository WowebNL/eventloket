<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 87482f34-1e1f-4853-b2da-312c9b2cebf0
 *
 * @openforms-rule-description Als ({{isHetAantalAanwezigenBijUwEvenementMinderDanSdf}} is gelijk aan 'Nee')of ({{vindenDeActivite…
 */
final class AlsIsGelijkAanNeeOfVindendeactivite implements Rule
{
    public function identifier(): string
    {
        return '87482f34-1e1f-4853-b2da-312c9b2cebf0';
    }

    public function triggerStepUuids(): array
    {
        return ['d87c01ce-8387-43b0-a8c8-e6cf5abb6da1'];
    }

    public function effectStepUuids(): array
    {
        return ['d87c01ce-8387-43b0-a8c8-e6cf5abb6da1', '5f986f16-6a3a-4066-9383-d71f09877f47'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf') === 'Nee') || ($s->get('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen') === 'Nee') || ($s->get('WordtErAlleenMuziekGeluidGeproduceerdTussen') === 'Nee') || ($s->get('IsdeGeluidsproductieLagerDan') === 'Nee') || ($s->get('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten') === 'Nee') || ($s->get('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst') === 'Nee') || ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Nee') || ($s->get('meldingvraag1') === 'Nee') || ($s->get('meldingvraag2') === 'Nee') || ($s->get('meldingvraag3') === 'Nee') || ($s->get('meldingvraag4') === 'Nee') || ($s->get('meldingvraag5') === 'Nee') || ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Ja'));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('contentGoNext', false);
        $s->setStepApplicable('5f986f16-6a3a-4066-9383-d71f09877f47', false);
        $s->setVariable('isVergunningaanvraag', true);
        $s->setFieldHidden('MeldingTekst', true);
    }
}
