<?php

declare(strict_types=1);

/**
 * InfoText is de centrale factory voor uitleg-blokjes op de wizard-stappen.
 * Deze tests dekken het kerngedrag dat alle 56 oude TextEntry-blokken
 * vervangt: variant-class, placeholder-interpolatie en hidden-label.
 */

use App\EventForm\Components\InfoText;
use App\EventForm\State\FormState;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\HtmlString;

function infoTextRender(TextEntry $entry, FormState $state): string
{
    $stub = new class($state)
    {
        public function __construct(private readonly FormState $state) {}

        public function state(): FormState
        {
            return $this->state;
        }
    };

    $reflection = new ReflectionObject($entry);
    $prop = $reflection->getProperty('getConstantStateUsing');
    $prop->setAccessible(true);
    $closure = $prop->getValue($entry);

    return (string) $closure($stub);
}

test('info-variant wraps content in eventform-alert-info wrapper', function () {
    $entry = InfoText::info('voorbeeld', '<p>Wist u dat...</p>');

    $output = infoTextRender($entry, FormState::empty());

    expect($output)->toContain('class="eventform-alert eventform-alert-info"')
        ->and($output)->toContain('<p>Wist u dat...</p>');
});

test('warning-variant wraps content in eventform-alert-warning wrapper', function () {
    $entry = InfoText::warning('letop', '<p>Let op: deadline.</p>');

    $output = infoTextRender($entry, FormState::empty());

    expect($output)->toContain('class="eventform-alert eventform-alert-warning"')
        ->and($output)->toContain('<p>Let op: deadline.</p>');
});

test('placeholders worden geïnterpoleerd op basis van FormState', function () {
    $state = new FormState(values: ['watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest Testlaan']);

    $entry = InfoText::info('intro', '<p>Voor evenement {{ watIsDeNaamVanHetEvenementVergunning }} geldt het volgende.</p>');
    $output = infoTextRender($entry, $state);

    expect($output)->toContain('Voor evenement Buurtfeest Testlaan')
        ->and($output)->not->toContain('{{ watIsDeNaamVanHetEvenementVergunning }}');
});

test('output is een HtmlString zodat Filament de HTML niet escape\'t', function () {
    $entry = InfoText::info('x', '<ul><li>één</li><li>twee</li></ul>');

    $reflection = new ReflectionObject($entry);
    $prop = $reflection->getProperty('getConstantStateUsing');
    $prop->setAccessible(true);
    $closure = $prop->getValue($entry);

    $stub = new class
    {
        public function state(): FormState
        {
            return FormState::empty();
        }
    };

    expect($closure($stub))->toBeInstanceOf(HtmlString::class);
});

test('XSS-payload in geïnterpoleerd veld wordt geëscapet — geen rauwe <script> in de output', function () {
    // Een organisator vult `<script>alert(1)</script>` in als
    // `naam_evenement`. Filament rendert de InfoText-state via
    // HtmlString rauw in de DOM; zonder escape zou dat tot self-XSS
    // leiden. Deze test bewijst dat de waarde nu als platte tekst
    // verschijnt.
    $state = new FormState(values: [
        'watIsDeNaamVanHetEvenementVergunning' => '<script>alert(1)</script>',
    ]);

    $entry = InfoText::info('xss', '<p>Voor {{ watIsDeNaamVanHetEvenementVergunning }} geldt dit.</p>');
    $output = infoTextRender($entry, $state);

    expect($output)->toContain('&lt;script&gt;')
        ->and($output)->not->toContain('<script>alert(1)</script>');
});

test('label is verborgen — anders verschijnt de field-name als kop', function () {
    $entry = InfoText::info('intern_key_nooit_zichtbaar', '<p>tekst</p>');

    expect($entry->isLabelHidden())->toBeTrue();
});
