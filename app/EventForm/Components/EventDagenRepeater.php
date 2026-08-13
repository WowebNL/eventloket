<?php

declare(strict_types=1);

namespace App\EventForm\Components;

use App\EventForm\Support\DagenRepeater;
use App\EventForm\Support\EventDagen;
use Closure;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Carbon;

/**
 * Per-day start and end times for a period that runs over several days.
 *
 * The two datetime pickers above the repeater stay authoritative: they decide
 * which days exist and own the very first start and the very last end. The
 * rows only add what those two moments cannot express, namely when each
 * individual day starts and ends. Rows are therefore generated rather than
 * added by hand, and the two mirrored times are shown but not editable.
 *
 * @see DagenRepeater for the state translation
 * @see EventDagen for the night-boundary rule that decides what "multi-day" means
 */
final class EventDagenRepeater
{
    public static function make(string $key, string $startKey, string $eindKey, string $label): Repeater
    {
        return Repeater::make($key)
            ->label($label)
            ->addable(false)
            ->deletable(false)
            ->reorderable(false)
            ->columns(2)
            ->itemLabel(fn (array $state): ?string => self::dagLabel($state['datum'] ?? null))
            // Drafts and copied applications from before this feature have a
            // period but no day rows yet. Build them on open so the organiser
            // still gets to fill them in.
            ->afterStateHydrated(function (?array $state, Repeater $component, Get $get) use ($startKey, $eindKey): void {
                if (is_array($state) && $state !== []) {
                    return;
                }

                $rijen = DagenRepeater::sync($get($startKey), $get($eindKey));

                if ($rijen !== []) {
                    $component->state($rijen);
                }
            })
            ->helperText('De eindtijd mag in de nacht liggen. Vult u een eindtijd in die niet later is dan de starttijd, dan loopt de dag door tot de volgende ochtend.')
            ->schema([
                Hidden::make('datum'),
                TimePicker::make('startTijd')
                    ->label('Starttijd')
                    ->seconds(false)
                    ->required()
                    ->disabled(fn (Get $get): bool => DagenRepeater::isEersteDag($get('datum'), $get("../../{$startKey}")))
                    ->dehydrated()
                    ->helperText(fn (Get $get): ?string => DagenRepeater::isEersteDag($get('datum'), $get("../../{$startKey}"))
                        ? 'Overgenomen uit de startdatum en -tijd hierboven.'
                        : null),
                TimePicker::make('eindTijd')
                    ->label('Eindtijd')
                    ->seconds(false)
                    ->required()
                    ->disabled(fn (Get $get): bool => DagenRepeater::isLaatsteDag($get('datum'), $get("../../{$startKey}"), $get("../../{$eindKey}")))
                    ->dehydrated()
                    ->helperText(fn (Get $get): ?string => DagenRepeater::isLaatsteDag($get('datum'), $get("../../{$startKey}"), $get("../../{$eindKey}"))
                        ? 'Overgenomen uit de einddatum en -tijd hierboven.'
                        : null)
                    ->rule(static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        if (! is_string($value) || $value === '') {
                            return;
                        }

                        if (! EventDagen::rolloverBinnenNachtGrens($get('startTijd'), $value)) {
                            $fail('Een dag die doorloopt tot na middernacht moet uiterlijk om 06:00 eindigen. Duurt de dag langer, pas dan de einddatum van de periode aan.');
                        }
                    }),
            ])
            ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_array($value)) {
                    return;
                }

                if (self::heeftOverlappendeDagen($value)) {
                    $fail('De ingevulde dagen overlappen elkaar. Een dag die doorloopt tot na middernacht moet eindigen voordat de volgende dag begint.');
                }
            })
            ->visible(fn (Get $get): bool => EventDagen::isMeerdaags($get($startKey), $get($eindKey)));
    }

    /**
     * Regenerate the rows of a repeater from its envelope. Call this from the
     * `afterStateUpdated()` of both datetime pickers that bound the period.
     */
    public static function sync(Get $get, Set $set, string $key, string $startKey, string $eindKey): void
    {
        $bestaand = $get($key);

        $set($key, DagenRepeater::sync(
            $get($startKey),
            $get($eindKey),
            is_array($bestaand) ? $bestaand : [],
        ));
    }

    /**
     * @param  array<array-key, mixed>  $rijen
     */
    private static function heeftOverlappendeDagen(array $rijen): bool
    {
        $blokken = DagenRepeater::naarReferenceData($rijen);

        for ($i = 1; $i < count($blokken); $i++) {
            $start = Carbon::parse($blokken[$i]['start']);
            $vorigEind = Carbon::parse($blokken[$i - 1]['eind']);

            if ($start->lessThan($vorigEind)) {
                return true;
            }
        }

        return false;
    }

    private static function dagLabel(mixed $datum): ?string
    {
        if (! is_string($datum) || $datum === '') {
            return null;
        }

        try {
            return ucfirst(Carbon::parse($datum)->translatedFormat('l j F Y'));
        } catch (\Throwable) {
            return null;
        }
    }
}
