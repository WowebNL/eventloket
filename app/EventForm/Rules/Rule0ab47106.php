<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 0ab47106-f334-492a-b676-a98ca88c2a64
 *
 * @openforms-rule-description
 */
final class Rule0ab47106 implements Rule
{
    public function identifier(): string
    {
        return '0ab47106-f334-492a-b676-a98ca88c2a64';
    }

    public function triggerStepUuids(): array
    {
        return ['ae44ab5b-c068-4ceb-b121-6e6907f78ef9'];
    }

    public function effectStepUuids(): array
    {
        return ['8a5fb30f-287e-41a2-a9bc-e7340bdaaa99'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A32') === true));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('aanpassenLocatieEnOfVerwijderenStraatmeubilair', false);
        $s->setStepApplicable('8a5fb30f-287e-41a2-a9bc-e7340bdaaa99', true);
    }
}
