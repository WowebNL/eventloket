<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 6b7d79c6-f543-40f0-9f76-eefd940f9794
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0981')en ({{wordenErGebiedsontslui…
 */
final class AlsIsGelijkAanGm0981EnWordenergebiedsontslui implements Rule
{
    public function identifier(): string
    {
        return '6b7d79c6-f543-40f0-9f76-eefd940f9794';
    }

    public function triggerStepUuids(): array
    {
        return ['d87c01ce-8387-43b0-a8c8-e6cf5abb6da1'];
    }

    public function effectStepUuids(): array
    {
        return [];
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('evenementInGemeente.brk_identification') === 'GM0981') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend39');
    }
}
