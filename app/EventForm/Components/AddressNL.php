<?php

declare(strict_types=1);

namespace App\EventForm\Components;

use App\Services\LocatieserverService;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Herbruikbare NL-adresvelden met postcode+huisnummer → PDOK auto-fill.
 *
 * Genereert een Fieldset met:
 *  - postcode (required, maxLength 6)
 *  - huisnummer (required, numeric)
 *  - huisletter (optional)
 *  - huisnummertoevoeging (optional)
 *  - straatnaam (auto-filled via PDOK, bewerkbaar)
 *  - woonplaatsnaam (auto-filled via PDOK)
 *
 * Alle veld-keys leven onder `{$key}.*` zodat de form-state zich gedraagt
 * als een nested object — identiek aan OF's `addressNL`-componenttype.
 */
final class AddressNL
{
    /**
     * Sub-veld-keys die `AddressNL::make()` onder de gegeven prefix aanmaakt.
     * Openbaar gemaakt zodat tests (en de transpiler) erop kunnen introspecteren
     * zonder een volledig gerenderde Filament-schema-context nodig te hebben.
     */
    public const SUBFIELDS = [
        'postcode',
        'huisnummer',
        'huisletter',
        'huisnummertoevoeging',
        'straatnaam',
        'woonplaatsnaam',
    ];

    public const REQUIRED_SUBFIELDS = [
        'postcode',
        'huisnummer',
    ];

    /** @return list<string> */
    public static function fieldKeys(string $prefix): array
    {
        return array_map(
            static fn (string $sub): string => "{$prefix}.{$sub}",
            self::SUBFIELDS,
        );
    }

    public static function make(string $key, ?string $label = null): Fieldset
    {
        return Fieldset::make($label ?? 'Adres')
            ->schema([
                TextInput::make("{$key}.postcode")
                    ->label('Postcode')
                    ->required()
                    ->maxLength(7)
                    ->live(onBlur: true)
                    ->afterStateUpdated(self::lookupCallback($key)),
                TextInput::make("{$key}.huisnummer")
                    ->label('Huisnummer')
                    ->required()
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(self::lookupCallback($key)),
                TextInput::make("{$key}.huisletter")
                    ->label('Huisletter')
                    ->maxLength(1)
                    ->live(onBlur: true)
                    ->afterStateUpdated(self::lookupCallback($key)),
                TextInput::make("{$key}.huisnummertoevoeging")
                    ->label('Toevoeging')
                    ->maxLength(10)
                    ->live(onBlur: true)
                    ->afterStateUpdated(self::lookupCallback($key)),
                TextInput::make("{$key}.straatnaam")
                    ->label('Straatnaam')
                    ->maxLength(255),
                TextInput::make("{$key}.woonplaatsnaam")
                    ->label('Plaats')
                    ->maxLength(255),
            ]);
    }

    /**
     * Bouwt de after-state-updated closure die op blur van postcode of
     * huisnummer de straat/plaats uit PDOK haalt en in de form-state zet.
     */
    private static function lookupCallback(string $key): \Closure
    {
        return function (Get $get, Set $set) use ($key): void {
            $postcode = $get("{$key}.postcode");
            $huisnummer = $get("{$key}.huisnummer");

            if (! is_string($postcode) || $postcode === '') {
                return;
            }
            if ($huisnummer === null || $huisnummer === '') {
                return;
            }

            $bag = app(LocatieserverService::class)->getBagObjectByPostcodeHuisnummer(
                $postcode,
                (string) $huisnummer,
                is_string($get("{$key}.huisletter")) ? $get("{$key}.huisletter") : null,
                is_string($get("{$key}.huisnummertoevoeging")) ? $get("{$key}.huisnummertoevoeging") : null,
            );

            if ($bag === null) {
                return;
            }

            $set("{$key}.straatnaam", $bag->straatnaam);
            $set("{$key}.woonplaatsnaam", $bag->woonplaatsnaam);
            if ($bag->huisletter !== null && $bag->huisletter !== '') {
                $set("{$key}.huisletter", $bag->huisletter);
            }
            if ($bag->huisnummertoevoeging !== null && $bag->huisnummertoevoeging !== '') {
                $set("{$key}.huisnummertoevoeging", $bag->huisnummertoevoeging);
            }
        };
    }
}
