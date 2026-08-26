<?php

use Filament\Facades\Filament;

/**
 * Fingerprints of the two logo generations. The current artwork is identified
 * by its viewBox and accent colour; the previous one by palette entries that
 * only occurred there.
 */
const CURRENT_LOGO_VIEWBOX = 'viewBox="0 0 1372.65 404.18"';
const CURRENT_LOGO_ACCENT = '#fd6814';
const PREVIOUS_LOGO_COLOURS = ['#7B0E0A', '#935A11', '#18437F', '#1A99C4', '#EE9212', '#DC141F', '#F0E517', '#908B1A'];

/**
 * The brand logo files carry a version suffix. Replacing the artwork means
 * writing it to files with the next suffix (v3, v4, ...) and updating every
 * reference, so that the asset URL changes and browsers cannot keep serving
 * the previous artwork from their cache.
 */
const BRAND_LOGO_VERSION = 'v2';

function brandLogoPath(string $variant): string
{
    return public_path('images/logos/logo-'.$variant.'-'.BRAND_LOGO_VERSION.'.svg');
}

test('both brand logo variants carry the current artwork', function (string $variant) {
    expect(brandLogoPath($variant))->toBeFile();

    $svg = file_get_contents(brandLogoPath($variant));

    expect($svg)
        ->toContain(CURRENT_LOGO_VIEWBOX)
        ->toContain(CURRENT_LOGO_ACCENT);

    foreach (PREVIOUS_LOGO_COLOURS as $colour) {
        expect($svg)->not->toContain($colour);
    }
})->with(['dark', 'light']);

test('the dark mode variant differs from the default variant only in the wordmark fills', function () {
    $default = file_get_contents(brandLogoPath('dark'));
    $darkMode = file_get_contents(brandLogoPath('light'));

    expect($darkMode)->not->toBe($default);

    // The wordmark (cls-2) and its counters (cls-3) swap colours; every path in
    // the file has to stay byte-identical, otherwise the two variants drifted.
    $stripStyles = fn (string $svg): string => preg_replace('/<style>.*<\/style>/s', '', $svg);

    expect($stripStyles($darkMode))->toBe($stripStyles($default));
});

test('every panel points at the brand logo files', function (string $panel) {
    $logos = Filament::getPanel($panel);

    expect($logos->getBrandLogo())->toEndWith('/images/logos/logo-dark-v2.svg')
        ->and($logos->getDarkModeBrandLogo())->toEndWith('/images/logos/logo-light-v2.svg');
})->with(['admin', 'municipality', 'advisor', 'organiser']);
