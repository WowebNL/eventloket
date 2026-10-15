<?php

declare(strict_types=1);

namespace App\EventForm\Components;

use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;

/**
 * Tells the organiser whether the submission is in time, on whichever step
 * concluded which deadline applies: the risk-scan step on the permit path,
 * the report step on the report path.
 *
 * Both paths get the same block rather than a near-copy per step, so the
 * green/amber distinction and the wording stay in one place. Only the noun
 * differs, and it follows the grounds the deadline was derived from.
 */
final class IndieningstermijnInfoText
{
    public static function make(string $name): TextEntry
    {
        return TextEntry::make($name)
            ->hiddenLabel()
            ->state(function ($livewire): ?HtmlString {
                $status = $livewire->state()->get('indieningstermijnStatus');

                if (! is_array($status)) {
                    return null;
                }

                return new HtmlString(sprintf(
                    '<div class="eventform-alert eventform-alert-%s">%s</div>',
                    $status['withinDeadline'] ? 'success' : 'warning',
                    self::body($status),
                ));
            })
            ->hidden(fn ($livewire): bool => $livewire->state()->get('indieningstermijnStatus') === null);
    }

    /**
     * @param  array{withinDeadline: bool, weeks: int, weeksRemaining: int, basedOn: string}  $status
     */
    private static function body(array $status): string
    {
        $isMelding = $status['basedOn'] === 'report';
        $indiening = $isMelding ? 'melding' : 'aanvraag';
        $weken = '<strong>'.$status['weeks'].' weken</strong>';

        if ($status['withinDeadline']) {
            return '<p>Uw '.$indiening.' valt binnen de indieningstermijn van '.$weken.' voor de startdatum van het evenement.</p>';
        }

        $termijn = $isMelding
            ? 'de indieningstermijn voor een melding'
            : 'de indieningstermijn voor deze risicoclassificatie';

        return '<p>Let op: '.$termijn.' is '.$weken.' voor de startdatum van het evenement. Uw '.$indiening.' valt buiten deze termijn. U kunt de '.$indiening.' nog steeds indienen, maar de kans op afwijzing is groter.</p>';
    }
}
