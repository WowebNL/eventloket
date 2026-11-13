<?php

declare(strict_types=1);

/**
 * The calendar assets have to come from the installed package version: the
 * calendar component bundles its own copy of the calendar library, and its
 * stylesheet has to match that copy.
 */

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;

test('the calendar registers no separate copy of the calendar library', function () {
    expect(FilamentAsset::getScripts(['guava/calendar'], withCore: false))->toBe([]);
});

test('the calendar stylesheet comes from the installed package', function () {
    $styles = FilamentAsset::getStyles(['guava/calendar']);

    expect($styles)->toHaveCount(1)
        ->and($styles[0])->toBeInstanceOf(Css::class)
        ->and($styles[0]->isRemote())->toBeFalse()
        ->and($styles[0]->getPath())->toBe(base_path('vendor/guava/calendar/dist/css/calendar.css'));
});
