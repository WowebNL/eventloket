<?php

declare(strict_types=1);

namespace App\EventForm\Schema;

use App\EventForm\Template\LabelRenderer;
use Closure;

/**
 * Closure-factory voor `->label()` op form-componenten waarvan het label
 * een template is met interpolaties ({{ veld }} / {% get_value %}).
 *
 * Vervangt het overal herhaalde
 *   ->label(fn ($livewire): string => app(LabelRenderer::class)->render('...', $livewire->state()))
 * door
 *   ->label(Label::render('...'))
 *
 * De template-string blijft bewust inline bij de component (dat is waar
 * 'ie hoort); alleen het closure-boilerplate rond de LabelRenderer-aanroep
 * is hier gecentraliseerd.
 */
final class Label
{
    public static function render(string $template): Closure
    {
        return static fn ($livewire): string => app(LabelRenderer::class)->render($template, $livewire->state());
    }
}
