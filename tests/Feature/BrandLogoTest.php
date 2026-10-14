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
 * The brand logo and favicon files carry a version suffix. Replacing the
 * artwork means writing it to files with the next suffix (v3, v4, ...) and
 * updating every reference, so that the asset URL changes and browsers cannot
 * keep serving the previous artwork from their cache. Only public/favicon.ico
 * stays unversioned, because browsers request that path on their own.
 */
const BRAND_LOGO_VERSION = 'v2';

function brandLogoPath(string $variant): string
{
    return public_path('images/logos/logo-'.$variant.'-'.BRAND_LOGO_VERSION.'.svg');
}

function faviconPath(string $extension): string
{
    return public_path('images/logos/favicon-'.BRAND_LOGO_VERSION.'.'.$extension);
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

test('the vector favicon is the square brand mark', function () {
    expect(faviconPath('svg'))->toBeFile();

    $svg = file_get_contents(faviconPath('svg'));

    // The mark is drawn with the same gradient and accent colour as the logo,
    // and carries none of the wordmark, so a single file works on any backdrop.
    expect($svg)
        ->toContain(CURRENT_LOGO_ACCENT)
        ->toContain('linear-gradient')
        ->not->toContain('cls-2')
        ->not->toContain('cls-3');

    preg_match('/viewBox="(\S+) (\S+) (\S+) (\S+)"/', $svg, $viewBox);

    expect($viewBox)->not->toBeEmpty()
        ->and((float) $viewBox[3])->toBe((float) $viewBox[4]);
});

test('the raster favicon is a valid icon with the small sizes browsers ask for', function () {
    expect(faviconPath('ico'))->toBeFile();

    $ico = file_get_contents(faviconPath('ico'));

    expect(strlen($ico))->toBeGreaterThan(0);

    ['reserved' => $reserved, 'type' => $type, 'count' => $count] =
        unpack('vreserved/vtype/vcount', substr($ico, 0, 6));

    expect($reserved)->toBe(0)
        ->and($type)->toBe(1)          // 1 = icon, 2 = cursor
        ->and($count)->toBeGreaterThan(0);

    $sizes = [];

    for ($i = 0; $i < $count; $i++) {
        $entry = unpack(
            'Cwidth/Cheight/Ccolours/Creserved/vplanes/vbits/Vbytes/Voffset',
            substr($ico, 6 + ($i * 16), 16)
        );

        // A zero byte means 256 pixels; every entry has to sit inside the file.
        $sizes[] = $entry['width'] === 0 ? 256 : $entry['width'];

        expect($entry['bytes'])->toBeGreaterThan(0)
            ->and($entry['offset'] + $entry['bytes'])->toBeLessThanOrEqual(strlen($ico));
    }

    expect($sizes)->toContain(16)->toContain(32);
});

test('the root favicon is served and matches the versioned icon', function () {
    // Browsers request /favicon.ico without being told to, so that file has to
    // hold the icon as well, next to the versioned copy that pages link to.
    expect(public_path('favicon.ico'))->toBeFile();

    expect(file_get_contents(public_path('favicon.ico')))
        ->not->toBeEmpty()
        ->toBe(file_get_contents(faviconPath('ico')));
});

test('every panel points at the favicon', function (string $panel) {
    expect(Filament::getPanel($panel)->getFavicon())
        ->toEndWith('/images/logos/favicon-v2.svg');
})->with(['admin', 'municipality', 'advisor', 'organiser']);

test('the standalone layouts link both favicon formats', function (string $view) {
    $blade = file_get_contents(resource_path('views/'.$view));

    expect($blade)
        ->toContain("asset('images/logos/favicon-v2.svg')")
        ->toContain("asset('images/logos/favicon-v2.ico')");
})->with(['welcome.blade.php', 'errors/custom-layout.blade.php']);
