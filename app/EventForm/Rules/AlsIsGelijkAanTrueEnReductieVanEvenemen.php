<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;
use App\EventForm\Transpiler\JsTruthy;

/**
 * @openforms-rule-uuid be547255-4a1b-4f37-96e8-919d5351e7a5
 *
 * @openforms-rule-description Als ({{inGemeentenResponse.line.start_end_equal}} is gelijk aan 'True')en ((reductie van {{evenemen…
 */
final class AlsIsGelijkAanTrueEnReductieVanEvenemen implements Rule
{
    public function identifier(): string
    {
        return 'be547255-4a1b-4f37-96e8-919d5351e7a5';
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
        return (bool) (($s->get('inGemeentenResponse.line.start_end_equal') === 'True') && ((is_array($s->get('evenementInGemeentenNamen')) ? count($s->get('evenementInGemeentenNamen')) : 0) >= 2) && JsTruthy::of($s->get('userSelectGemeente11')));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('userSelectGemeente', '');
    }
}
