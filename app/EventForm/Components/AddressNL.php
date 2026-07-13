<?php

declare(strict_types=1);

namespace App\EventForm\Components;

use App\Services\LocatieserverService;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
        'straatnaam',
        'woonplaatsnaam',
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
                    ->live(debounce: '750ms')
                    ->afterStateUpdated(self::lookupCallback($key)),
                TextInput::make("{$key}.huisnummer")
                    ->label('Huisnummer')
                    ->required()
                    ->numeric()
                    ->live(debounce: '750ms')
                    ->afterStateUpdated(self::lookupCallback($key)),
                TextInput::make("{$key}.huisletter")
                    ->label('Huisletter')
                    ->maxLength(1)
                    ->live(debounce: '750ms')
                    ->afterStateUpdated(self::lookupCallback($key)),
                TextInput::make("{$key}.huisnummertoevoeging")
                    ->label('Toevoeging')
                    ->maxLength(10)
                    ->live(debounce: '750ms')
                    ->afterStateUpdated(self::lookupCallback($key)),
                // Straatnaam and woonplaatsnaam are auto-filled by the PDOK
                // lookup, but that call can fail to fire; making them required
                // forces a fallback where the organiser fills them in manually.
                TextInput::make("{$key}.straatnaam")
                    ->label('Straatnaam')
                    ->required()
                    ->maxLength(255),
                TextInput::make("{$key}.woonplaatsnaam")
                    ->label('Plaats')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    /**
     * Whether the PDOK lookup has enough input to be worthwhile: it needs both
     * a postcode and a huisnummer. Huisletter and huisnummertoevoeging only
     * refine an already-valid postcode + huisnummer combination.
     */
    public static function hasLookupInput(mixed $postcode, mixed $huisnummer): bool
    {
        return is_string($postcode) && $postcode !== ''
            && $huisnummer !== null && $huisnummer !== '';
    }

    /**
     * Bouwt de after-state-updated closure die, na een debounce op één van de
     * adresvelden, de straat/plaats uit PDOK haalt en in de form-state zet.
     * Doet niets zolang postcode en huisnummer niet allebei gevuld zijn.
     */
    private static function lookupCallback(string $key): \Closure
    {
        return function (Get $get, Set $set) use ($key): void {
            $postcode = $get("{$key}.postcode");
            $huisnummer = $get("{$key}.huisnummer");

            if (! self::hasLookupInput($postcode, $huisnummer)) {
                return;
            }

            $service = app(LocatieserverService::class);
            $huisletter = is_string($get("{$key}.huisletter")) ? $get("{$key}.huisletter") : null;
            $huisnummertoevoeging = is_string($get("{$key}.huisnummertoevoeging")) ? $get("{$key}.huisnummertoevoeging") : null;

            $bag = $service->getBagObjectByPostcodeHuisnummer(
                (string) $postcode,
                (string) $huisnummer,
                $huisletter,
                $huisnummertoevoeging,
            );

            if ($bag !== null) {
                $set("{$key}.straatnaam", $bag->straatnaam);
                $set("{$key}.woonplaatsnaam", $bag->woonplaatsnaam);
                if ($bag->huisletter !== null && $bag->huisletter !== '') {
                    $set("{$key}.huisletter", $bag->huisletter);
                }
                if ($bag->huisnummertoevoeging !== null && $bag->huisnummertoevoeging !== '') {
                    $set("{$key}.huisnummertoevoeging", $bag->huisnummertoevoeging);
                }

                return;
            }

            // No exact match. When only the huisletter/toevoeging refinement
            // failed but the plain postcode + house number does resolve, the
            // street/city are still valid, so leave them untouched. Only when
            // the base combination itself does not exist do we clear the
            // previously auto-filled street/city (so no stale address lingers)
            // and tell the organiser they can fill it in manually.
            if (($huisletter !== null && $huisletter !== '') || ($huisnummertoevoeging !== null && $huisnummertoevoeging !== '')) {
                $baseBag = $service->getBagObjectByPostcodeHuisnummer((string) $postcode, (string) $huisnummer);
                if ($baseBag !== null) {
                    return;
                }
            }

            $set("{$key}.straatnaam", null);
            $set("{$key}.woonplaatsnaam", null);

            Notification::make()
                ->title('Geen adres gevonden')
                ->body('Er is geen adres gevonden voor deze postcode en huisnummer. Controleer de invoer of vul straat en plaats zelf in.')
                ->warning()
                ->send();
        };
    }
}
