<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid d21486ca-b7b2-4a4c-9963-1f24ca7eeea4
 *
 * @openforms-rule-description Als {{waarVindtHetEvenementPlaats}} is gelijk aan 'None'
 */
final class AlsIsGelijkAanNone implements Rule
{
    public function identifier(): string
    {
        return 'd21486ca-b7b2-4a4c-9963-1f24ca7eeea4';
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
        return (bool) ((($s->get('waarVindtHetEvenementPlaats11') === '{\'route\': False, \'buiten\': False, \'gebouw\': False}') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') !== 'Nee')));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('addressToCheck', 'None');
    }
}
