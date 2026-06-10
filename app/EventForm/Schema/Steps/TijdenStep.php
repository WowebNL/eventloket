<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\InfoText;
use App\EventForm\Components\JaNeeOptions;
use App\EventForm\Schema\Hidden;
use App\EventForm\Schema\Label;
use App\EventForm\State\FormState;
use App\EventForm\Template\LabelRenderer;
use App\Filament\Organiser\Pages\Calendar;
use App\Models\Organisation;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid 00f09aee-fedd-44d6-b82c-3e3754d67b7a
 *
 * @openforms-step-index 3
 */
final class TijdenStep
{
    public const UUID = '00f09aee-fedd-44d6-b82c-3e3754d67b7a';

    public static function make(): Step
    {
        return Step::make('Tijden')
            ->key(self::UUID)
            ->schema([
                InfoText::warning('content2', '<p>Let op, gemeenten hanteren niet allemaal dezelfde indieningstermijnen. Gemiddeld geldt minimaal 8 weken voor een klein A-evenement, 13 weken voor een middelgroot B-Evenement en 23 weken voor een groot C-evenement. Check voor de exacte termijnen bij je gemeente.</p>'),
                Grid::make(1)
                    ->schema([
                        DateTimePicker::make('EvenementStart')
                            ->label(Label::render('Wat is de start datum en tijdstip van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->seconds(false)
                            ->minDate(today())
                            ->afterOrEqual(today()->toDateString())
                            ->validationMessages([
                                'after_or_equal' => 'De startdatum van het evenement moet vandaag of later zijn.',
                            ])
                            ->required()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (! $state) {
                                    return;
                                }
                                $month = Carbon::parse($state)->month;
                                $set('inWelkSeizoenVindtHetEvenementPlaats', in_array($month, [3, 4, 5, 9, 10, 11], strict: true) ? '0.25' : '0.5');
                            })
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'Dit is het startmoment wanneer u bezoekers of deelnemers verwacht.',
                            ])
                            ->live(),
                        DateTimePicker::make('EvenementEind')
                            ->label(Label::render('Wat is de eind datum en tijdstip van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->seconds(false)
                            ->minDate(fn (Get $get) => $get('EvenementStart') ?: today())
                            ->afterOrEqual('EvenementStart')
                            ->validationMessages([
                                'after_or_equal' => 'De einddatum van het evenement moet op of na de startdatum liggen.',
                            ])
                            ->required()
                            ->belowContent([
                                Icon::make(Heroicon::InformationCircle),
                                'Dit is het eindmoment wanneer u bezoekers of deelnemers verwacht.',
                            ])
                            ->live(),
                    ]),
                InfoText::info('evenmentenInDeBuurtContent', function (FormState $state): string {
                    $html = app(LabelRenderer::class)->renderHtml(
                        '<p>Uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} heeft o.a. de volgende gelijktijdig geplande evenementen <strong>{{ evenementenInDeGemeente }} </strong>binnen de gemeente {% get_value evenementInGemeente \'name\' %}.&nbsp;</p>',
                        $state,
                    );

                    // Link naar de evenementenkalender via de route i.p.v.
                    // een hardcoded omgevings-URL. De tenant komt uit de
                    // form-state; buiten een ingelogde organiser-context
                    // (bv. PDF-rendering in een queue-job) laten we de
                    // link weg en blijft alleen de tekst over.
                    $organisation = $state->get('authOrganisation');
                    if ($organisation instanceof Organisation) {
                        $html .= sprintf(
                            '<p>Controleer <a href="%s" target="_blank" rel="noopener noreferrer">de evenementen kalender</a> om te bepalen of u uw planning wilt aanpassen.</p>',
                            e(Calendar::getUrl(panel: 'organiser', tenant: $organisation)),
                        );
                    } else {
                        $html .= '<p>Controleer de evenementen kalender om te bepalen of u uw planning wilt aanpassen.</p>';
                    }

                    return $html;
                })
                    ->hidden(Hidden::rule('evenmentenInDeBuurtContent')),
                Radio::make('zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten')
                    ->label(Label::render('Zijn er voorafgaand aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} opbouwactiviteiten?'))
                    ->options(JaNeeOptions::OPTIONS)
                    ->required()
                    ->live(),
                Grid::make(1)
                    ->schema([
                        DateTimePicker::make('OpbouwStart')
                            ->label(Label::render('Wat is de start datum en tijd van de opbouw uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->seconds(false)
                            ->minDate(today())
                            ->maxDate(fn (Get $get) => $get('OpbouwEind') ?: $get('EvenementStart'))
                            ->beforeOrEqual('OpbouwEind')
                            ->validationMessages([
                                'before_or_equal' => 'De starttijd van de opbouw moet op of voor de eindtijd opbouw liggen.',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('OpbouwStart');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten') === 'Ja');
                            })
                            ->live(),
                        DateTimePicker::make('OpbouwEind')
                            ->label(Label::render('Wat is de eind datum en tijd van de opbouw van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->seconds(false)
                            ->minDate(fn (Get $get) => $get('OpbouwStart') ?: today())
                            ->maxDate(fn (Get $get) => $get('EvenementStart'))
                            ->beforeOrEqual('EvenementStart')
                            ->validationMessages([
                                'before_or_equal' => 'De eindtijd van de opbouw moet op of voor de startdatum van het evenement liggen.',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('OpbouwEind');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten') === 'Ja');
                            })
                            ->live(),
                    ]),
                Radio::make('zijnErTijdensHetEvenementXOpbouwactiviteiten')
                    ->label(Label::render('Zijn er tijdens het evenement {{ watIsDeNaamVanHetEvenementVergunning }} opbouwactiviteiten?'))
                    ->options(JaNeeOptions::OPTIONS)
                    ->required(),
                Radio::make('zijnErAansluitendAanHetEvenementAfbouwactiviteiten')
                    ->label(Label::render('Zijn er aansluitend aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} afbouwactiviteiten?'))
                    ->options(JaNeeOptions::OPTIONS)
                    ->required()
                    ->live(),
                Grid::make(1)
                    ->schema([
                        DateTimePicker::make('AfbouwStart')
                            ->label(Label::render('Wat is de start datum en tijdstip van de afbouw uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->seconds(false)
                            ->minDate(fn (Get $get) => $get('EvenementEind') ?: today())
                            ->maxDate(fn (Get $get) => $get('AfbouwEind'))
                            ->afterOrEqual('EvenementEind')
                            ->validationMessages([
                                'after_or_equal' => 'De starttijd van de afbouw moet op of na de einddatum van het evenement liggen.',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('AfbouwStart');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('zijnErAansluitendAanHetEvenementAfbouwactiviteiten') === 'Ja');
                            })
                            ->live(),
                        DateTimePicker::make('AfbouwEind')
                            ->label(Label::render('Wat is de eind datum en tijdstip van de afbouw van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?'))
                            ->seconds(false)
                            ->minDate(fn (Get $get) => $get('AfbouwStart') ?: $get('EvenementEind'))
                            ->afterOrEqual('AfbouwStart')
                            ->validationMessages([
                                'after_or_equal' => 'De eindtijd van de afbouw moet op of na de starttijd van de afbouw liggen.',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('AfbouwEind');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('zijnErAansluitendAanHetEvenementAfbouwactiviteiten') === 'Ja');
                            })
                            ->live(),
                    ]),
                Radio::make('zijnErTijdensHetEvenementXAfbouwactiviteiten3')
                    ->label(Label::render('Zijn er tijdens het evenement {{ watIsDeNaamVanHetEvenementVergunning }} afbouwactiviteiten?'))
                    ->options(JaNeeOptions::OPTIONS)
                    ->required(),
                TextEntry::make('overzichtTijden')
                    ->hiddenLabel()
                    // `renderHtml()` ipv `render()` — de output wordt rauw
                    // in de DOM gezet via `HtmlString`. DateTimePicker-
                    // waarden zijn ISO-strings (geen user-text-input),
                    // dus self-XSS is in de praktijk niet mogelijk, maar
                    // safe-by-default is het beleid.
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->renderHtml('<h2>Overzicht ingevulde tijden</h2><figure class="table"><table><thead><tr><th><strong>Activiteit</strong></th><th>&nbsp;</th><th><strong>Start</strong></th><th>&nbsp;</th><th><strong>Eind</strong></th></tr></thead><tbody><tr><th><strong>Opbouw</strong></th><td>&nbsp;</td><td>{{ OpbouwStart }}</td><td>&nbsp;</td><td>{{ OpbouwEind }}</td></tr><tr><th><strong>Publiek</strong></th><td>&nbsp;</td><td>{{ EvenementStart }}</td><td>&nbsp;</td><td>{{ EvenementEind }}</td></tr><tr><th><strong>Afbouw</strong></th><td>&nbsp;</td><td>{{ AfbouwStart }}</td><td>&nbsp;</td><td>{{ AfbouwEind }}</td></tr></tbody></table></figure><p><br>Wijzig de velden boven dit overzicht indien de tijden niet correct zijn.</p>', $livewire->state()))),
            ]);
    }
}
