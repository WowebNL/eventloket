<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\Schema\Steps\AanvraagOfMeldingStep;
use App\EventForm\Schema\Steps\MeldingStep;
use App\EventForm\State\FormState;

final class VergunningSchakeltMeldingUit implements Rule
{
    public function identifier(): string
    {
        return 'vergunning-schakelt-melding-uit';
    }

    public function triggerStepUuids(): array
    {
        return [AanvraagOfMeldingStep::UUID];
    }

    public function effectStepUuids(): array
    {
        return [MeldingStep::UUID];
    }

    public function applies(FormState $state): bool
    {
        if ($state->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging') {
            return false;
        }

        return $state->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Ja';
    }

    public function apply(FormState $state): void
    {
        $state->setStepApplicable(MeldingStep::UUID, false);
    }
}
