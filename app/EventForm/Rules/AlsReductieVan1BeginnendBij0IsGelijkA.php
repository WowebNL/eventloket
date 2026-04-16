<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid a6fcec40-74f6-4741-862f-22ebf2de7142
 *
 * @openforms-rule-description Als (reductie van {{evenementInGemeentenNamen}} (1 + {{accumulator}}, beginnend bij 0)) is gelijk a…
 */
final class AlsReductieVan1BeginnendBij0IsGelijkA implements Rule
{
    public function identifier(): string
    {
        return 'a6fcec40-74f6-4741-862f-22ebf2de7142';
    }

    public function triggerStepUuids(): array
    {
        return [];
    }

    public function effectStepUuids(): array
    {
        return [];
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((is_array($s->get('evenementInGemeentenNamen')) ? count($s->get('evenementInGemeentenNamen')) : 0) === 1);
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('evenementInGemeente', $s->get('inGemeentenResponse.all.items.0'));
    }
}
