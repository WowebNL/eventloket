<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 91bf1bff-b1af-4da7-b310-e56854d48f61
 *
 * @openforms-rule-description Als {{meldingAdres}} is niet gelijk aan "{'postcode': '', 'houseLetter': '', 'houseNumber': '', 'ho…
 */
final class AlsIsNietGelijkAanPostcodeHouseletterHousenumberHo implements Rule
{
    public function identifier(): string
    {
        return '91bf1bff-b1af-4da7-b310-e56854d48f61';
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
        return (bool) ((($s->get('meldingAdres') !== '{\'postcode\': \'\', \'houseLetter\': \'\', \'houseNumber\': \'\', \'houseNumberAddition\': \'\'}') && ($s->get('meldingAdres') !== '{\'postcode\': \'\', \'houseLetter\': \'\', \'houseNumber\': \'\', \'houseNumberAddition\': \'\', \'city\': \'\', \'streetName\': \'\', \'secretStreetCity\': \'\'}') && ($s->get('meldingAdres') !== 'None') && ($s->get('waarVindtHetEvenementPlaats11') === '{\'route\': False, \'buiten\': False, \'gebouw\': False}')));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('addressToCheck', $s->get('meldingAdres'));
    }
}
