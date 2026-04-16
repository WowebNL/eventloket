<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 457c34ac-d4ac-4037-83b2-eaea58d24ccb
 *
 * @openforms-rule-description
 */
final class Rule457c34ac implements Rule
{
    public function identifier(): string
    {
        return '457c34ac-d4ac-4037-83b2-eaea58d24ccb';
    }

    public function triggerStepUuids(): array
    {
        return ['ae44ab5b-c068-4ceb-b121-6e6907f78ef9'];
    }

    public function effectStepUuids(): array
    {
        return ['7982e106-bce0-49cf-bdaa-ada9eac8b6ba'];
    }

    public function applies(FormState $s): bool
    {
        return (bool) ($s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A50') === true);
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('bebordingsEnBewegwijzeringsplan', false);
    }
}
