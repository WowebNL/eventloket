<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Template\LabelRenderer;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid 8a5fb30f-287e-41a2-a9bc-e7340bdaaa99
 *
 * @openforms-step-index 12
 */
final class VergunningaanvraagMaatregelenStep
{
    public const UUID = '8a5fb30f-287e-41a2-a9bc-e7340bdaaa99';

    public static function make(): Step
    {
        return Step::make('Vergunningaanvraag: maatregelen')
            ->key(self::UUID)
            ->schema([
                Fieldset::make('Aanpassen locatie en/of verwijderen straatmeubilair')
                    ->schema([
                        TextEntry::make('content29')
                            ->hiddenLabel()
                            ->state(new HtmlString('<p>U heeft aangekruisd: (Laten) aanpassen locatie en/of verwijderen straatmeubilair.</p>'))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('content29');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Textarea::make('geefEenOmschrijvingWelkeAanpassingenOpLocatieEvenementXWaarNodigZijnOfWelkStraatmeubilairUWiltVerwijderenOfAanpassen')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Geef een omschrijving welke aanpassingen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }} waar nodig zijn of welk straatmeubilair u wilt verwijderen of aanpassen.', $livewire->state()))
                            ->required()
                            ->maxLength(10000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('geefEenOmschrijvingWelkeAanpassingenOpLocatieEvenementXWaarNodigZijnOfWelkStraatmeubilairUWiltVerwijderenOfAanpassen');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('aanpassenLocatieEnOfVerwijderenStraatmeubilair');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                Fieldset::make('Extra afval')
                    ->schema([
                        TextEntry::make('content30')
                            ->hiddenLabel()
                            ->state(new HtmlString('<p><strong>U heeft aangegeven, dat er extra afval ontstaat op uw Evenement {{ watIsDeNaamVanHetEvenementVergunning }}. Hieronder volgen een aantal vragen daarover.</strong></p>'))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('content30');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Repeater::make('wieMaaktDeLocatiesEnDeOmgevingDaarvanSchoonEnWanneerGebeurtDat')
                            ->label('Wie maakt de locaties en de omgeving daarvan schoon, en wanneer gebeurt dat?')
                            ->schema([
                                TextInput::make('locatieAfval')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('locatieAfval');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('doorWieAfval')
                                    ->label('Door wie?')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('doorWieAfval');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                DateTimePicker::make('starttijdSchoonmaak')
                                    ->label('Starttijd schoonmaak')
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('starttijdSchoonmaak');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                DateTimePicker::make('eindtijdSchoonmaak')
                                    ->label('Eindtijd schoonmaak')
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('eindtijdSchoonmaak');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('wieMaaktDeLocatiesEnDeOmgevingDaarvanSchoonEnWanneerGebeurtDat');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('hoeveelExtraAfvalinzamelpuntenGaatUOpLocatieEvenementXPlaatsen')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Hoeveel extra afvalinzamelpunten gaat u op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}. plaatsen?', $livewire->state()))
                            ->numeric()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('hoeveelExtraAfvalinzamelpuntenGaatUOpLocatieEvenementXPlaatsen');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Radio::make('doetUAanAfvalscheidingOpLocatieEvenementX')
                            ->label(fn ($livewire): string => app(LabelRenderer::class)->render('Doet u aan afvalscheiding op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?', $livewire->state()))
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('doetUAanAfvalscheidingOpLocatieEvenementX');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Radio::make('voertUDeSchoonmaakZelfUit')
                            ->label('Voert u de schoonmaak zelf uit? ')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('voertUDeSchoonmaakZelfUit');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            })
                            ->live(),
                        FileUpload::make('uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen')
                            ->label('U kunt het afvalplan hier uploaden of later als bijlage toevoegen.')
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (! ($get('voertUDeSchoonmaakZelfUit') === 'Ja'));
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('extraAfval');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                Fieldset::make('Gemeentelijke hulpmiddelen')
                    ->schema([
                        Radio::make('wilUGebruikMakenVanGemeentelijkeHulpmiddelen')
                            ->label('Wil U gebruik maken van gemeentelijke hulpmiddelen?')
                            ->options([
                                'Ja' => 'Ja',
                                'Nee' => 'Nee',
                            ])
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('wilUGebruikMakenVanGemeentelijkeHulpmiddelen');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            })
                            ->live(),
                        Fieldset::make('Veldengroep')
                            ->schema([
                                TextEntry::make('content37')
                                    ->hiddenLabel()
                                    ->state(new HtmlString('<p>Vermeld hier van welke materialen u gebruik zou willen maken en ook de aantallen. Uw betreffende gemeente zal aangeven welke hulpmiddelen aangeboden kunnen worden.</p>'))
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('content37');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('dranghekken1')
                                    ->label('Dranghekken')
                                    ->numeric()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('dranghekken1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('wegafzettingen1')
                                    ->label('Wegafzettingen')
                                    ->numeric()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('wegafzettingen1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('vlaggen1')
                                    ->label('Vlaggen')
                                    ->numeric()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('vlaggen1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('vlaggenmasten1')
                                    ->label('Vlaggenmasten')
                                    ->numeric()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('vlaggenmasten1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('parkeerverbodsborden1')
                                    ->label('Parkeerverbodsborden')
                                    ->numeric()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('parkeerverbodsborden1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('bordenGeslotenVerklaring1')
                                    ->label('Borden gesloten verklaring')
                                    ->numeric()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('bordenGeslotenVerklaring1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('bordenEenrichtingsweg1')
                                    ->label('Borden eenrichtingsweg')
                                    ->numeric()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('bordenEenrichtingsweg1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                Radio::make('wenstUTegenBetalingStroomAfTeNemenVanDeGemeente1')
                                    ->label('Wenst u tegen betaling stroom af te nemen van de gemeente?')
                                    ->options([
                                        'Ja' => 'Ja',
                                        'Nee' => 'Nee',
                                    ])
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('wenstUTegenBetalingStroomAfTeNemenVanDeGemeente1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                Textarea::make('geefAanOpWelkeLocatieUStroomWilt1')
                                    ->label('Geef aan op welke locatie u stroom wilt afnemen')
                                    ->required()
                                    ->maxLength(10000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('geefAanOpWelkeLocatieUStroomWilt1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (! ($get('wenstUTegenBetalingStroomAfTeNemenVanDeGemeente') === 'Ja'));
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('veldengroep2');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (! ($get('wilUGebruikMakenVanGemeentelijkeHulpmiddelen') === 'Ja'));
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('gemeentelijkeHulpmiddelen');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
            ]);
    }
}
