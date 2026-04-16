<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 1ceeb0f8-0e80-42b6-82a5-1c8001312d64
 *
 * @openforms-rule-description Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1954')en ({{wordenErGebiedsontslui…
 */
final class AlsIsGelijkAanGm1954EnWordenergebiedsontslui implements Rule
{
    public function identifier(): string
    {
        return '1ceeb0f8-0e80-42b6-82a5-1c8001312d64';
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
        return (bool) ((($s->get('evenementInGemeente.brk_identification') === 'GM1954') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')));
    }

    public function apply(FormState $s): void
    {
        $s->setSystem('registration_backend', 'backend11');
    }
}
