<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;
use App\EventForm\Transpiler\JsTruthy;

/**
 * @openforms-rule-uuid 580a3ef8-9fa6-4f5a-8714-502d86d6cb55
 *
 * @openforms-rule-description Als bool({{userSelectGemeente}})en ({{userSelectGemeente}} is niet gelijk aan 'None')
 */
final class AlsBoolEnIsNietGelijkAanNone580a3ef8 implements Rule
{
    public function identifier(): string
    {
        return '580a3ef8-9fa6-4f5a-8714-502d86d6cb55';
    }

    public function triggerStepUuids(): array
    {
        return ['2186344f-9821-45d1-bd52-9900ae15fcb6'];
    }

    public function effectStepUuids(): array
    {
        return [];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (JsTruthy::of($s->get('userSelectGemeente')) && ($s->get('userSelectGemeente') !== ''));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('evenementInGemeente', $s->get((string) (((string) 'gemeenten.').((string) $s->get('userSelectGemeente')))));
    }
}
