<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Template\LabelRenderer;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
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
                TextEntry::make('content2')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p><span >Let op, gemeenten hanteren niet allemaal dezelfde indieningstermijnen. Gemiddeld geldt minimaal 8 weken voor een klein A-evenement, 13 weken voor een middelgroot B-Evenement en 23 weken voor een groot C-evenement. Check voor de exacte termijnen bij je gemeente.</span></p>', $livewire->state()))),
                Grid::make(2)
                    ->schema([
                        DateTimePicker::make('EvenementStart')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Wat is de start datum en tijdstip van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->required(),
                        DateTimePicker::make('EvenementEind')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Wat is de eind datum en tijdstip van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->required(),
                    ]),
                TextEntry::make('evenmentenInDeBuurtContent')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} heeft o.a. de volgende gelijktijdig geplande evenementen <strong>{{ evenementenInDeGemeente }} </strong>binnen de gemeente {% get_value evenementInGemeente \'name\' %}.&nbsp;</p><p>Controleer <a href="https://eventloket.vrzl-test.woweb.app/organiser/{{eventloketSession.organiser_uuid}}/calendar" target="_blank" rel="noopener noreferrer">de evenementen kalender</a> om te bepalen of u uw planning wilt aanpassen.</p>', $livewire->state())))
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('evenmentenInDeBuurtContent') !== false),
                Radio::make('zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten')
                    ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Zijn er voorafgaand aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} opbouwactiviteiten?', $livewire->state()))
                    ->options([
                        'Ja' => 'Ja',
                        'Nee' => 'Nee',
                    ])
                    ->required()
                    ->live(),
                Grid::make(2)
                    ->schema([
                        DateTimePicker::make('OpbouwStart')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Wat is de start datum en tijd van de opbouw uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->required()
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('OpbouwStart');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten') === 'Ja');
                            }),
                        DateTimePicker::make('OpbouwEind')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Wat is de eind datum en tijd van de opbouw van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->required()
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('OpbouwEind');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten') === 'Ja');
                            }),
                    ]),
                Radio::make('zijnErTijdensHetEvenementXOpbouwactiviteiten')
                    ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Zijn er tijdens het evenement {{ watIsDeNaamVanHetEvenementVergunning }} opbouwactiviteiten?', $livewire->state()))
                    ->options([
                        'Ja' => 'Ja',
                        'Nee' => 'Nee',
                    ])
                    ->required(),
                Radio::make('zijnErAansluitendAanHetEvenementAfbouwactiviteiten')
                    ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Zijn er aansluitend aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} afbouwactiviteiten?', $livewire->state()))
                    ->options([
                        'Ja' => 'Ja',
                        'Nee' => 'Nee',
                    ])
                    ->required()
                    ->live(),
                Grid::make(2)
                    ->schema([
                        DateTimePicker::make('AfbouwStart')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Wat is de start datum en tijdstip van de afbouw uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->required()
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('AfbouwStart');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('zijnErAansluitendAanHetEvenementAfbouwactiviteiten') === 'Ja');
                            }),
                        DateTimePicker::make('AfbouwEind')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Wat is de eind datum en tijdstip van de afbouw van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->required()
                            ->hidden(function (Get $get, $livewire): bool {
                                $rule = $livewire->state()->isFieldHidden('AfbouwEind');
                                if ($rule !== null) {
                                    return $rule;
                                }

                                return ! ($get('zijnErAansluitendAanHetEvenementAfbouwactiviteiten') === 'Ja');
                            }),
                    ]),
                Radio::make('zijnErTijdensHetEvenementXAfbouwactiviteiten3')
                    ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Zijn er tijdens het evenement {{ watIsDeNaamVanHetEvenementVergunning }} afbouwactiviteiten?', $livewire->state()))
                    ->options([
                        'Ja' => 'Ja',
                        'Nee' => 'Nee',
                    ])
                    ->required(),
                TextEntry::make('overzichtTijden')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<h2>Overzicht ingevulde tijden</h2><figure class="table"><table><thead><tr><th><strong>Activiteit</strong></th><th>&nbsp;</th><th><strong>Start</strong></th><th>&nbsp;</th><th><strong>Eind</strong></th></tr></thead><tbody><tr><th><strong>Opbouw</strong></th><td>&nbsp;</td><td>{{ OpbouwStart }}</td><td>&nbsp;</td><td>{{ OpbouwEind }}</td></tr><tr><th><strong>Publiek</strong></th><td>&nbsp;</td><td>{{ EvenementStart }}</td><td>&nbsp;</td><td>{{ EvenementEind }}</td></tr><tr><th><strong>Afbouw</strong></th><td>&nbsp;</td><td>{{ AfbouwStart }}</td><td>&nbsp;</td><td>{{ AfbouwEind }}</td></tr></tbody></table></figure><p><br>Wijzig de velden boven dit overzicht indien de tijden niet correct zijn.</p>', $livewire->state()))),
            ]);
    }
}
