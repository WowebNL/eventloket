<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid a757ea1f-24ee-40b8-a839-4e9997a33959
 *
 * @openforms-rule-description Als {{meldingsvraag5}} is gelijk aan 'Ja'
 */
final class AlsIsGelijkAanJa implements Rule
{
    public function identifier(): string
    {
        return 'a757ea1f-24ee-40b8-a839-4e9997a33959';
    }

    public function triggerStepUuids(): array
    {
        return [];
    }

    public function effectStepUuids(): array
    {
        return ['d87c01ce-8387-43b0-a8c8-e6cf5abb6da1'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('meldingsvraag5') === 'Ja'));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', false);
    }
}
