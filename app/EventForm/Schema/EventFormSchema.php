<?php

declare(strict_types=1);

namespace App\EventForm\Schema;

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
 * Compositie-factory voor het complete evenementformulier: 17 stappen
 * in de volgorde waarop ze in OF staan. Elke stap leeft in z'n eigen
 * gegenereerde klasse; deze factory plakt ze samen.
 */
class EventFormSchema
{
    /**
     * @return list<Step>
     */
    public static function steps(): array
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
            TypeAanvraagStep::make(),
        ];
    }
}
