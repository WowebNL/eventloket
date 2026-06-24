<?php

declare(strict_types=1);

namespace App\EventForm\Schema\CustomSteps;

use App\EventForm\Reporting\SubmissionReport;
use App\EventForm\Schema\EventFormSchema;
use App\EventForm\State\FormState;
use Filament\Forms\Components\Checkbox;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * Samenvatting-stap, vóór de Type-aanvraag-stap. Toont alle ingevulde
 * waarden per wizard-stap (zelfde indeling als de submission-PDF) en
 * eindigt met een verplichte AVG-akkoord-checkbox: zonder dat vinkje
 * kan een organisator niet doorklikken naar Indienen.
 *
 * Hand-geschreven (geen OF-equivalent). Staat buiten de
 * `app/EventForm/Schema/Steps/`-directory — dat was oorspronkelijk
 * nodig omdat de (inmiddels verwijderde) transpiler die map bij elke
 * run wiste; de plaatsing is nu puur organisatorisch.
 */
final class SamenvattingStep
{
    public const UUID = 'samenvatting-pre-indienen';

    public static function make(): Step
    {
        return Step::make('Samenvatting')
            ->key(self::UUID)
            ->schema([
                TextEntry::make('samenvattingOverzicht')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(self::renderHtml($livewire->state())))
                    ->columnSpanFull(),
                Checkbox::make('akkoordVerwerkingGegevens')
                    ->label('Ik ga akkoord dat mijn gegevens verwerkt worden voor de behandeling van deze aanvraag.')
                    ->required()
                    ->accepted()
                    ->validationMessages([
                        'accepted' => 'U moet akkoord gaan met de verwerking van uw gegevens om de aanvraag in te kunnen dienen.',
                        'required' => 'U moet akkoord gaan met de verwerking van uw gegevens om de aanvraag in te kunnen dienen.',
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Bouw de samenvatting-HTML uit `SubmissionReport`-secties. Het
     * rendrenden delegeren we aan een blade-partial zodat:
     *   - de structuur 1-op-1 spiegelt met de submission-PDF
     *     (titel + per sectie tabel, plus aparte takken voor
     *     `table` en `sub` entries);
     *   - kaart-SVG's uit Map-velden worden meegenomen (Filament
     *     rendert de HtmlString in de browser → de `<img>`-data-URI
     *     uit `renderGeoJsonSvg()` werkt direct).
     */
    private static function renderHtml(FormState $state): string
    {
        $sections = app(SubmissionReport::class)->build($state, EventFormSchema::stepsForReport());
        $risicoClassificatie = $state->get('risicoClassificatie');
        $indieningstermijnStatus = $state->get('indieningstermijnStatus');

        return view('event-form.samenvatting', [
            'sections' => $sections,
            'risicoClassificatie' => $risicoClassificatie,
            'indieningstermijnStatus' => $indieningstermijnStatus,
        ])->render();
    }
}
