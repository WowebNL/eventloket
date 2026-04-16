<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 03a87183-48c3-4e5b-b6ec-287c4f3daf97
 *
 * @openforms-rule-description
 */
final class Rule03a87183 implements Rule
{
    public function identifier(): string
    {
        return '03a87183-48c3-4e5b-b6ec-287c4f3daf97';
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
        return (bool) ($s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A33') === true);
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('extraAfval', false);
        $s->setStepApplicable('8a5fb30f-287e-41a2-a9bc-e7340bdaaa99', true);
    }
}
