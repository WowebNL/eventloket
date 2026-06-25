<?php

declare(strict_types=1);

namespace App\EventForm\Components;

use App\EventForm\State\FormState;
use App\EventForm\Template\LabelRenderer;
use Closure;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;

/**
 * Factory voor uniforme info- en warning-alerts in het evenementformulier.
 *
 * Vervangt het `TextEntry::make(...)->state(fn ... => new HtmlString(...))`
 * boilerplate dat in elke step terugkwam. Nu één plek waar de wrapper-class
 * + LabelRenderer-interpolatie geregeld worden, zodat alle uitleg-blokjes
 * consequent gestyled zijn (zie `.eventform-alert` in de organiser-theme).
 *
 * Kies bewust:
 *   - `info`    → context, uitleg, achtergrond. De default voor alles wat
 *                 "goed om te weten" is maar niet acuut.
 *   - `success` → positieve bevestiging, bijv. "u bent op tijd".
 *   - `warning` → "Let op", deadlines, consequenties, weigeringen. Spaarzaam
 *                 inzetten zodat het z'n urgentie-signaal houdt.
 *
 * Bullets (`<ul><li>`) en links worden netjes gerenderd door de CSS; je
 * hoeft alleen de HTML in te leveren zoals voorheen.
 */
final class InfoText
{
    /**
     * @param  string|Closure(FormState): string  $html
     *                                                   Statische string → wordt door LabelRenderer geïnterpoleerd
     *                                                   (`{{ veldnaam }}`, `{% if %}`, etc.). Closure → ontvangt de
     *                                                   FormState en moet zelf de HTML-body bouwen. Gebruik de Closure-
     *                                                   variant zodra je conditionele lijsten / takken hebt — anders
     *                                                   propt je hele if-elif-else-cascade als template-syntax in een
     *                                                   string en wordt 't onleesbaar.
     */
    public static function info(string $name, string|Closure $html): TextEntry
    {
        return self::build($name, $html, 'info');
    }

    /**
     * @param  string|Closure(FormState): string  $html
     */
    public static function success(string $name, string|Closure $html): TextEntry
    {
        return self::build($name, $html, 'success');
    }

    /**
     * @param  string|Closure(FormState): string  $html
     */
    public static function warning(string $name, string|Closure $html): TextEntry
    {
        return self::build($name, $html, 'warning');
    }

    /**
     * @param  string|Closure(FormState): string  $html
     */
    private static function build(string $name, string|Closure $html, string $variant): TextEntry
    {
        return TextEntry::make($name)
            ->hiddenLabel()
            ->state(function ($livewire) use ($html, $variant): HtmlString {
                $body = $html instanceof Closure
                    // Closure-pad: de developer bouwt de HTML zelf en is
                    // verantwoordelijk voor escape van eventuele user-input
                    // (gebruik `e()` rondom state-waarden).
                    ? (string) $html($livewire->state())
                    // String-pad: LabelRenderer interpoleert + escape't
                    // automatisch via `renderHtml()` zodat user-input als
                    // platte tekst verschijnt.
                    : app(LabelRenderer::class)->renderHtml($html, $livewire->state());

                return new HtmlString(sprintf(
                    '<div class="eventform-alert eventform-alert-%s">%s</div>',
                    $variant,
                    $body,
                ));
            });
    }
}
