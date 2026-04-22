<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;
use App\EventForm\Transpiler\JsTruthy;

/**
 * @openforms-rule-uuid 99b8a502-9ef8-4be2-8142-2a25c69ba905
 *
 * @openforms-rule-description Als bool({{addressToCheck}})en ({{addressToCheck}} is niet gelijk aan 'None')
 */
final class AlsBoolEnIsNietGelijkAanNone99b8a502 implements Rule
{
    public function identifier(): string
    {
        return '99b8a502-9ef8-4be2-8142-2a25c69ba905';
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
        return (bool) (JsTruthy::of($s->get('addressToCheck')) && ($s->get('addressToCheck') !== 'None') && ($s->get('waarVindtHetEvenementPlaats11') !== '{\'gebouw\': False, \'buiten\': False, \'route\': True}'));
    }

    public function apply(FormState $s): void
    {
        app(ServiceFetcher::class)->fetch('inGemeentenResponse', $s);
    }
}
