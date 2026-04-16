<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 8e022b2c-1742-4ff7-a5a0-50d02d05833e
 *
 * @openforms-rule-description Als ({{isHetAantalAanwezigenBijUwEvenementMinderDanSdf}} is gelijk aan 'Ja')of ({{vindenDeActivitei…
 */
final class AlsIsGelijkAanJaOfVindendeactivitei implements Rule
{
    public function identifier(): string
    {
        return '8e022b2c-1742-4ff7-a5a0-50d02d05833e';
    }

    public function triggerStepUuids(): array
    {
        return ['d87c01ce-8387-43b0-a8c8-e6cf5abb6da1'];
    }

    public function effectStepUuids(): array
    {
        return ['d87c01ce-8387-43b0-a8c8-e6cf5abb6da1'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('contentGoNext', true);
        $s->setFieldHidden('MeldingTekst', false);
    }
}
