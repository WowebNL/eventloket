<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Template\LabelRenderer;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid d790edb5-712a-4f83-87a8-1a86e4831455
 *
 * @openforms-step-index 11
 */
final class VergunningsaanvraagVoorwerpenStep
{
    public const UUID = 'd790edb5-712a-4f83-87a8-1a86e4831455';

    public static function make(): Step
    {
        return Step::make('Vergunningsaanvraag: voorwerpen')
            ->key(self::UUID)
            ->schema([
                Fieldset::make('Voorwerpen')
                    ->schema([
                        TextEntry::make('content27')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven, dat er diverse voorwerpen geplaatst worden. Wilt u hier de aantallen en locaties (indien meerdere) invullen?</p>', $livewire->state())))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('content27');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Repeater::make('verkooppuntenToegangsKaarten')
                            ->label('Verkooppunten toegangs-kaarten')
                            ->schema([
                                TextInput::make('locatieVerkooppuntToegangskaart')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('locatieVerkooppuntToegangskaart');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('aantapVerkoopuntenToegangskaarten')
                                    ->label('Aantal verkoopunten')
                                    ->numeric()
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('aantapVerkoopuntenToegangskaarten');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('verkooppuntenToegangsKaarten');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return true || (false);
                            }),
                        Repeater::make('verkooppuntenMuntenEnBonnen')
                            ->label('Verkooppunten munten en bonnen')
                            ->schema([
                                TextInput::make('locatieVerkooppuntMuntenBonnen')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('locatieVerkooppuntMuntenBonnen');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('aantapVerkoopuntenMuntenBonnen')
                                    ->label('Aantal verkoopunten')
                                    ->numeric()
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('aantapVerkoopuntenMuntenBonnen');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('verkooppuntenMuntenEnBonnen');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return true || (false);
                            }),
                        Repeater::make('verkooppuntenCashless')
                            ->label('Verkooppunten cashless')
                            ->schema([
                                TextInput::make('locatieVerkooppuntCashless')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('locatieVerkooppuntCashless');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('aantapVerkoopuntenCashless')
                                    ->label('Aantal verkoopunten')
                                    ->numeric()
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('aantapVerkoopuntenCashless');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('verkooppuntenCashless');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return true || (false);
                            }),
                        Repeater::make('Speeltoestellen')
                            ->label('Speeltoestellen')
                            ->schema([
                                TextInput::make('locatiespeeltoestellen')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('locatiespeeltoestellen');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('aantalSpeeltoestellen')
                                    ->label('Aantal speeltoestellen')
                                    ->numeric()
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('aantalSpeeltoestellen');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('Speeltoestellen');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return true || (false);
                            }),
                        Repeater::make('brandstofopslag')
                            ->label('Brandstofopslag')
                            ->schema([
                                TextInput::make('locatiebrandstofopslag')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('locatiebrandstofopslag');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('aantalbrandstofopslag')
                                    ->label('Aantal brandstofopslag')
                                    ->numeric()
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('aantalbrandstofopslag');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('brandstofopslag');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return true || (false);
                            }),
                        Repeater::make('geluidstorens')
                            ->label('Geluidstorens')
                            ->schema([
                                TextInput::make('locatieGeluidstoren')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('locatieGeluidstoren');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('aantalGeluidstoren')
                                    ->label('Aantal geluidstorens')
                                    ->numeric()
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('aantalGeluidstoren');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('geluidstorens');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return true || (false);
                            }),
                        Repeater::make('Lichtmasten')
                            ->label('Lichtmasten')
                            ->schema([
                                TextInput::make('locatieLichtmast')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('locatieLichtmast');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('aantalLichtmast')
                                    ->label('Aantal lichtmasten')
                                    ->numeric()
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('aantalLichtmast');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('Lichtmasten');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return true || (false);
                            }),
                        Repeater::make('marktkramen')
                            ->label('Marktkramen')
                            ->schema([
                                TextInput::make('locatieMarktkraam')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('locatieMarktkraam');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('aantalMarktkraam')
                                    ->label('Aantal marktkramen')
                                    ->numeric()
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('aantalMarktkraam');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('marktkramen');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return true || (false);
                            }),
                        Repeater::make('andersGroup')
                            ->label('Anders')
                            ->schema([
                                TextInput::make('locatieAnders')
                                    ->label('Locatie')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('locatieAnders');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('aantalAnders')
                                    ->label('Aantal anders')
                                    ->numeric()
                                    ->required()
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('aantalAnders');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('andersGroup');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return true || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('voorwerpen');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                Fieldset::make('Brandgevaarlijke stoffen')
                    ->schema([
                        TextEntry::make('content28')
                            ->hiddenLabel()
                            ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>U heeft aangegeven, dat er sprake is van Aggregaten,&nbsp; brandstofopslag en andere brandgevaarlijke stoffen. Denk aan :</p><ul><li>Aggregaten</li><li>Brandstofopslag</li><li>Gasflessen</li><li>Frituur</li><li>Houtskoolbarbecue</li><li>Open vuur (vuurplaats, vuurkorven)</li><li>Vuurwerk</li><li>Carbid-, kanon- en kamerschieten</li><li>Materiaal voor showeffecten</li></ul>', $livewire->state())))
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('content28');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Repeater::make('welkeStoffenGebruiktU')
                            ->label('Welke stoffen gebruikt u?')
                            ->schema([
                                TextInput::make('typeStof')
                                    ->label('Type stof')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('typeStof');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('plaatsStof')
                                    ->label('Plaats')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('plaatsStof');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('opslagwijzeStof')
                                    ->label('Opslagwijze')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('opslagwijzeStof');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('toelichtingStof')
                                    ->label('Toelichting')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('toelichtingStof');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('welkeStoffenGebruiktU');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('brandgevaarlijkeStoffen');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
            ]);
    }
}
