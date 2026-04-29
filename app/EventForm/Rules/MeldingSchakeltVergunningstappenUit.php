<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\Schema\Steps\AanvraagOfMeldingStep;
use App\EventForm\Schema\Steps\VergunningaanvraagMaatregelenStep;
use App\EventForm\Schema\Steps\VergunningaanvraagOverigStep;
use App\EventForm\Schema\Steps\VergunningaanvraagVervolgvragenStep;
use App\EventForm\Schema\Steps\VergunningsaanvraagExtraActiviteitenStep;
use App\EventForm\Schema\Steps\VergunningsaanvraagVoorwerpenStep;
use App\EventForm\Schema\Steps\VergunningsaanvraagVoorzieningenStep;
use App\EventForm\Schema\Steps\Vragenboom2Step;
use App\EventForm\State\FormState;

final class MeldingSchakeltVergunningstappenUit implements Rule
{
    /** @var list<string> */
    private const VERGUNNING_STAP_UUIDS = [
        Vragenboom2Step::UUID,
        VergunningaanvraagVervolgvragenStep::UUID,
        VergunningsaanvraagVoorzieningenStep::UUID,
        VergunningsaanvraagVoorwerpenStep::UUID,
        VergunningaanvraagMaatregelenStep::UUID,
        VergunningsaanvraagExtraActiviteitenStep::UUID,
        VergunningaanvraagOverigStep::UUID,
    ];

    public function identifier(): string
    {
        return 'melding-schakelt-vergunningstappen-uit';
    }

    public function triggerStepUuids(): array
    {
        return [AanvraagOfMeldingStep::UUID];
    }

    public function effectStepUuids(): array
    {
        return self::VERGUNNING_STAP_UUIDS;
    }

    public function applies(FormState $state): bool
    {
        if ($state->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging') {
            return false;
        }

        return $state->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee';
    }

    public function apply(FormState $state): void
    {
        foreach (self::VERGUNNING_STAP_UUIDS as $uuid) {
            $state->setStepApplicable($uuid, false);
        }
    }
}
