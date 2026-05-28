<?php

declare(strict_types=1);

namespace App\EventForm\Components;

/**
 * Centraal de Ja/Nee-keuze die door 44 Radio-velden in het evenement-
 * formulier gedeeld wordt. Voorheen schreven we het option-paar overal
 * letterlijk uit; één plek scheelt ~88 regels en geeft één punt om aan
 * te passen als we ooit i18n willen of een ander labelpaar.
 *
 * Bewust géén factory die een complete `Radio::make(...)` opbouwt — de
 * 44 velden verschillen sterk in `->live()`, `->hidden(...)`,
 * `->afterStateUpdated(...)` enzovoort, en een factory zou óf restrictief
 * worden óf onleesbaar door de parameter-overload.
 */
final class JaNeeOptions
{
    /** @var array<string, string> */
    public const OPTIONS = [
        'Ja' => 'Ja',
        'Nee' => 'Nee',
    ];
}
