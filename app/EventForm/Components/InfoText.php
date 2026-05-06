<?php

declare(strict_types=1);

namespace App\EventForm\Components;

use App\EventForm\Template\LabelRenderer;
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
 *   - `warning` → "Let op", deadlines, consequenties, weigeringen. Spaarzaam
 *                 inzetten zodat het z'n urgentie-signaal houdt.
 *
 * Bullets (`<ul><li>`) en links worden netjes gerenderd door de CSS; je
 * hoeft alleen de HTML in te leveren zoals voorheen.
 */
final class InfoText
{
    public static function info(string $name, string $html): TextEntry
    {
        return self::build($name, $html, 'info');
    }

    public static function warning(string $name, string $html): TextEntry
    {
        return self::build($name, $html, 'warning');
    }

    private static function build(string $name, string $html, string $variant): TextEntry
    {
        return TextEntry::make($name)
            ->hiddenLabel()
            ->state(fn ($livewire) => new HtmlString(sprintf(
                '<div class="eventform-alert eventform-alert-%s">%s</div>',
                $variant,
                app(LabelRenderer::class)->render($html, $livewire->state()),
            )));
    }
}
