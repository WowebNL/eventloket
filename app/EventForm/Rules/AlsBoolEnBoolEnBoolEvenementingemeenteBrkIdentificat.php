<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;
use App\EventForm\Support\JsTruthy;

/**
 * @openforms-rule-uuid 3fa0fbf5-9ee1-4c2a-9074-9993e208b010
 *
 * @openforms-rule-description Als bool({{EvenementStart}})en bool({{EvenementEind}})en bool({{evenementInGemeente.brk_identificat…
 */
final class AlsBoolEnBoolEnBoolEvenementingemeenteBrkIdentificat implements Rule
{
    public function identifier(): string
    {
        return '3fa0fbf5-9ee1-4c2a-9074-9993e208b010';
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
        return (bool) (JsTruthy::of($s->get('EvenementStart')) && JsTruthy::of($s->get('EvenementEind')) && JsTruthy::of($s->get('evenementInGemeente.brk_identification')));
    }

    public function apply(FormState $s): void
    {
        app(ServiceFetcher::class)->fetch('evenementenInDeGemeente', $s);
    }
}
