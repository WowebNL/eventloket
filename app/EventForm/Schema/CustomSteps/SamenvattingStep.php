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
 * Hand-geschreven (geen OF-equivalent) en bewust buiten de
 * `app/EventForm/Schema/Steps/`-directory geplaatst zodat
 * `transpile:event-form` 'm niet wist.
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
     * Bouw de samenvatting-HTML uit `SubmissionReport`-secties. Lege
     * secties (stappen waar niets ingevuld is) vallen automatisch weg
     * — geen "—"-rijen.
     */
    private static function renderHtml(FormState $state): string
    {
        $sections = app(SubmissionReport::class)->build($state, EventFormSchema::stepsForReport());
        if ($sections === []) {
            return '<p>U heeft nog geen velden ingevuld.</p>';
        }

        $html = '';
        foreach ($sections as $section) {
            $html .= '<h3 style="margin-top: 1.5rem; font-size: 1rem; font-weight: 600;">'
                .htmlspecialchars((string) $section['title'])
                .'</h3>';
            $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem;">';
            foreach ($section['entries'] as $entry) {
                $html .= '<tr>';
                $html .= '<td style="padding: 0.4rem 0.5rem; border-bottom: 1px solid #eee; color: #555; vertical-align: top; width: 40%;">'
                    .strip_tags((string) $entry['label'])
                    .'</td>';
                $html .= '<td style="padding: 0.4rem 0.5rem; border-bottom: 1px solid #eee; vertical-align: top;">'
                    .nl2br(htmlspecialchars((string) $entry['value']))
                    .'</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        return $html;
    }
}
