<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Template\LabelRenderer;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid 48e9408a-3455-4d3c-b9ce-5f6f08f8f2b5
 *
 * @openforms-step-index 0
 */
final class ContactgegevensStep
{
    public const UUID = '48e9408a-3455-4d3c-b9ce-5f6f08f8f2b5';

    public static function make(): Step
    {
        return Step::make('Contactgegevens')
            ->key(self::UUID)
            ->schema([
                TextEntry::make('loadUserInformation')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Een ogenblik geduld, uw gegevens worden ingeladen…</p>', $livewire->state())))
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('loadUserInformation');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                TextInput::make('watIsUwVoornaam')
                    ->label('Wat is uw voornaam?')
                    ->required()
                    ->maxLength(1000)
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('watIsUwVoornaam');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                TextInput::make('watIsUwAchternaam')
                    ->label('Wat is uw achternaam?')
                    ->required()
                    ->maxLength(1000)
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('watIsUwAchternaam');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                TextInput::make('watIsUwEMailadres')
                    ->label('Wat is uw e-mailadres?')
                    ->email()
                    ->required()
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('watIsUwEMailadres');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                TextInput::make('watIsUwTelefoonnummer')
                    ->label('Wat is uw telefoonnummer?')
                    ->required()
                    ->maxLength(1000)
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('watIsUwTelefoonnummer');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    }),
                Fieldset::make('Organisatie informatie')
                    ->schema([
                        TextInput::make('watIsHetKamerVanKoophandelNummerVanUwOrganisatie')
                            ->label('Wat is het Kamer van Koophandel nummer van uw organisatie?')
                            ->required()
                            ->maxLength(1000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('watIsHetKamerVanKoophandelNummerVanUwOrganisatie');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('watIsDeNaamVanUwOrganisatie')
                            ->label('Wat is de naam van uw organisatie?')
                            ->required()
                            ->maxLength(1000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('watIsDeNaamVanUwOrganisatie');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('postcode1')
                                    ->label('Postcode')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('postcode1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('huisletter1')
                                    ->label('Huisletter')
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('huisletter1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('straatnaam1')
                                    ->label('Straatnaam')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('straatnaam1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('huisnummer1')
                                    ->label('Huisnummer')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('huisnummer1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('huisnummertoevoeging1')
                                    ->label('Huisnummertoevoeging')
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('huisnummertoevoeging1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('plaatsnaam1')
                                    ->label('Plaatsnaam')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('plaatsnaam1');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('kolommen1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('emailadresOrganisatie')
                            ->label('Wat is het e-mailadres van uw organisatie?')
                            ->email()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('emailadresOrganisatie');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('telefoonnummerOrganisatie')
                            ->label('Wat is het telefoonnummer van uw organisatie?')
                            ->required()
                            ->maxLength(1000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('telefoonnummerOrganisatie');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('organisatieInformatie');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                TextEntry::make('waarschuwingGeenKvk')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p><strong>Let op: </strong>u vult dit formulier in op persoonlijke titel, hiermee ligt de verantwoordelijkheid voor de aanvraag ook bij u als persoon. U kunt deze aanvraag als bedrijf doen door linksboven op “Mijn omgeving” te klikken en een organisatie te registeren (of een bestaande te selecteren), vervolgens kunt u een nieuwe aanvraag starten.</p>', $livewire->state())))
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('waarschuwingGeenKvk');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                Fieldset::make('Adresgegevens')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('postcode')
                                    ->label('Postcode')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('postcode');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('huisletter')
                                    ->label('Huisletter')
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('huisletter');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('straatnaam')
                                    ->label('Straatnaam')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('straatnaam');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('land')
                                    ->label('Land')
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('land');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('huisnummer')
                                    ->label('Huisnummer')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('huisnummer');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('huisnummertoevoeging')
                                    ->label('Huisnummertoevoeging')
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('huisnummertoevoeging');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                                TextInput::make('plaatsnaam')
                                    ->label('Plaatsnaam')
                                    ->required()
                                    ->maxLength(1000)
                                    ->hidden(function (Get $get, $livewire) {
                                        $rule = $livewire->state()->isFieldHidden('plaatsnaam');
                                        if ($rule === true) {
                                            return true;
                                        } if ($rule === false) {
                                            return false;
                                        }

return false || (false);
                                    }),
                            ])
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('kolommen');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('adresgegevens');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return true || (false);
                    }),
                CheckboxList::make('extraContactpersonenToevoegen')
                    ->label('Extra contactpersonen toevoegen')
                    ->options([
                        'vooraf' => 'Contactpersoon voorafgaand aan het evenement',
                        'tijdens' => 'Contactpersoon tijdens het evenement',
                        'achteraf' => 'Contactpersoon na het evenement',
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('extraContactpersonenToevoegen');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (false);
                    })
                    ->live(),
                Fieldset::make('Contactpersoon voorafgaand aan het evenement')
                    ->schema([
                        TextInput::make('naam')
                            ->label('Naam')
                            ->required()
                            ->maxLength(1000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('naam');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('telefoonnummer')
                            ->label('Telefoonnummer')
                            ->tel()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('telefoonnummer');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('eMailadres')
                            ->label('E-mailadres')
                            ->email()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('eMailadres');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('contactpersoonVoorafgaandAanHetEvenement');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (! (in_array('vooraf', (array) $get('extraContactpersonenToevoegen'), true)));
                    }),
                Fieldset::make('Contactpersoon tijdens het evenement')
                    ->schema([
                        TextInput::make('naam1')
                            ->label('Naam')
                            ->required()
                            ->maxLength(1000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('naam1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('telefoonnummer1')
                            ->label('Telefoonnummer')
                            ->tel()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('telefoonnummer1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('eMailadres1')
                            ->label('E-mailadres')
                            ->email()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('eMailadres1');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('contactpersoonVoorafgaandAanHetEvenement1');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (! (in_array('tijdens', (array) $get('extraContactpersonenToevoegen'), true)));
                    }),
                Fieldset::make('Contactpersoon na het evenement')
                    ->schema([
                        TextInput::make('naam2')
                            ->label('Naam')
                            ->required()
                            ->maxLength(1000)
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('naam2');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('telefoonnummer2')
                            ->label('Telefoonnummer')
                            ->tel()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('telefoonnummer2');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                        TextInput::make('eMailadres2')
                            ->label('E-mailadres')
                            ->email()
                            ->required()
                            ->hidden(function (Get $get, $livewire) {
                                $rule = $livewire->state()->isFieldHidden('eMailadres2');
                                if ($rule === true) {
                                    return true;
                                } if ($rule === false) {
                                    return false;
                                }

return false || (false);
                            }),
                    ])
                    ->hidden(function (Get $get, $livewire) {
                        $rule = $livewire->state()->isFieldHidden('contactpersoonVoorafgaandAanHetEvenement2');
                        if ($rule === true) {
                            return true;
                        } if ($rule === false) {
                            return false;
                        }

return false || (! (in_array('achteraf', (array) $get('extraContactpersonenToevoegen'), true)));
                    }),
            ]);
    }
}
