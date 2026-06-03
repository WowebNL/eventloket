<?php

declare(strict_types=1);

namespace App\EventForm\Schema;

use App\EventForm\Schema\CustomSteps\SamenvattingStep;
use App\EventForm\Schema\Patches\LocatiePolygonsPatch;
use App\EventForm\Schema\Steps\AanvraagOfMeldingStep;
use App\EventForm\Schema\Steps\BijlagenStep;
use App\EventForm\Schema\Steps\ContactgegevensStep;
use App\EventForm\Schema\Steps\LocatieVanHetEvenement2Step;
use App\EventForm\Schema\Steps\MeldingStep;
use App\EventForm\Schema\Steps\NaamVanHetEvenementStep;
use App\EventForm\Schema\Steps\RisicoscanStep;
use App\EventForm\Schema\Steps\TijdenStep;
use App\EventForm\Schema\Steps\TypeAanvraagStep;
use App\EventForm\Schema\Steps\VergunningaanvraagMaatregelenStep;
use App\EventForm\Schema\Steps\VergunningaanvraagOverigStep;
use App\EventForm\Schema\Steps\VergunningaanvraagVervolgvragenStep;
use App\EventForm\Schema\Steps\VergunningsaanvraagExtraActiviteitenStep;
use App\EventForm\Schema\Steps\VergunningsaanvraagVoorwerpenStep;
use App\EventForm\Schema\Steps\VergunningsaanvraagVoorzieningenStep;
use App\EventForm\Schema\Steps\Vragenboom2Step;
use App\EventForm\Schema\Steps\WaarvoorWiltUHetEventloketGebruikenStep;
use App\Models\Organisation;
use Filament\Schemas\Components\Wizard\Step;

/**
 * Compositie-factory voor het complete evenementformulier: 18 stappen
 * (17 ooit getranspileerd uit OpenForms + 1 hand-geschreven Samenvatting
 * vlak voor de laatste 'Indienen'-actie). De step-klassen zijn sinds het
 * verwijderen van de transpiler gewoon hand-onderhouden code; deze factory
 * plakt ze in volgorde aan elkaar.
 *
 * Volgorde Type-aanvraag → Samenvatting: behandelaars / organisators
 * willen op de samenvatting eerst zien wélke aanvraag er gedaan wordt
 * (vergunning vs. melding vs. vooraankondiging + ontheffingen) voordat
 * 'ze de complete recapitulatie scannen.
 */
class EventFormSchema
{
    /**
     * @return list<Step>
     */
    public static function steps(?Organisation $organisation = null): array
    {
        return [
            ...self::stepsForReport($organisation),
            SamenvattingStep::make(),
        ];
    }

    /**
     * De data-collecterende + concluderende stappen — alle stappen
     * waarvan de inhoud op de samenvatting + in de PDF moet verschijnen.
     * `SamenvattingStep` zelf zit hier niet bij omdat 'ie juist deze
     * lijst rendert (dan zou 'ie zichzelf bevatten).
     *
     * `TypeAanvraagStep` heeft geen Field-componenten, maar
     * `SubmissionReport` herkent 'm en bouwt zelf een afgeleide
     * "Onderdelen aanvraag"-sectie op basis van de FormState.
     *
     * @return list<Step>
     */
    public static function stepsForReport(?Organisation $organisation = null): array
    {
        return [
            ContactgegevensStep::make(),
            NaamVanHetEvenementStep::make(),
            LocatiePolygonsPatch::apply(LocatieVanHetEvenement2Step::make()),
            TijdenStep::make(),
            WaarvoorWiltUHetEventloketGebruikenStep::make(),
            AanvraagOfMeldingStep::make(),
            MeldingStep::make(),
            RisicoscanStep::make(),
            Vragenboom2Step::make(),
            VergunningaanvraagVervolgvragenStep::make(),
            VergunningsaanvraagVoorzieningenStep::make(),
            VergunningsaanvraagVoorwerpenStep::make(),
            VergunningaanvraagMaatregelenStep::make(),
            VergunningsaanvraagExtraActiviteitenStep::make(),
            VergunningaanvraagOverigStep::make(),
            BijlagenStep::make($organisation),
            TypeAanvraagStep::make(),
        ];
    }

    /**
     * Statische lijst van step-UUIDs in de volgorde van de wizard.
     * Spiegel van `steps()` zonder Filament-Step-instances te bouwen,
     * voor contexten waar je geen mounted container hebt (bv.
     * `EventFormPage::resolveStartStep()` om uit een step-key de
     * 1-based positie af te leiden).
     *
     * @return list<string>
     */
    public static function stepUuidsInOrder(): array
    {
        return [
            ContactgegevensStep::UUID,
            NaamVanHetEvenementStep::UUID,
            LocatieVanHetEvenement2Step::UUID,
            TijdenStep::UUID,
            WaarvoorWiltUHetEventloketGebruikenStep::UUID,
            AanvraagOfMeldingStep::UUID,
            MeldingStep::UUID,
            RisicoscanStep::UUID,
            Vragenboom2Step::UUID,
            VergunningaanvraagVervolgvragenStep::UUID,
            VergunningsaanvraagVoorzieningenStep::UUID,
            VergunningsaanvraagVoorwerpenStep::UUID,
            VergunningaanvraagMaatregelenStep::UUID,
            VergunningsaanvraagExtraActiviteitenStep::UUID,
            VergunningaanvraagOverigStep::UUID,
            BijlagenStep::UUID,
            TypeAanvraagStep::UUID,
            SamenvattingStep::UUID,
        ];
    }
}
