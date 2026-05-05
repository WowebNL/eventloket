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
use Filament\Schemas\Components\Wizard\Step;

/**
 * Compositie-factory voor het complete evenementformulier: 18 stappen
 * (17 transpiled uit OF + 1 hand-geschreven Samenvatting vlak voor
 * Type-aanvraag). Elke OF-stap leeft in z'n eigen gegenereerde klasse;
 * deze factory plakt ze in volgorde aan elkaar.
 */
class EventFormSchema
{
    /**
     * @return list<Step>
     */
    public static function steps(): array
    {
        return [
            ...self::stepsForReport(),
            SamenvattingStep::make(),
            TypeAanvraagStep::make(),
        ];
    }

    /**
     * De data-collecterende stappen — alles waar de organisator velden
     * invult. Wordt door `SamenvattingStep` en `GenerateSubmissionPdf`
     * gebruikt om secties op te bouwen; Samenvatting + Type-aanvraag
     * laten we eruit omdat die geen nieuwe data bevatten (Samenvatting
     * toont juist de overige stappen, Type-aanvraag is een tonende
     * conclusion-tekst).
     *
     * @return list<Step>
     */
    public static function stepsForReport(): array
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
            BijlagenStep::make(),
        ];
    }
}
