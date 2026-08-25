<?php

declare(strict_types=1);

use App\EventForm\State\FormState;
use App\EventForm\Template\LabelRenderer;

/**
 * Anchors for the two sound level questions. The label template is read out
 * of the step file and then rendered, so both the exact wording and the
 * event name placeholder are covered by the same test.
 */
function labelTemplateFor(string $field): string
{
    $code = file_get_contents(app_path('EventForm/Schema/Steps/VergunningaanvraagVervolgvragenStep.php'));

    $pattern = "/TextInput::make\('".preg_quote($field, '/')."'\)\s*->label\(Label::render\('(?<template>[^']+)'\)\)/";

    expect((bool) preg_match($pattern, $code, $matches))->toBeTrue(
        "No Label::render template found for field [{$field}]."
    );

    return $matches['template'];
}

test('the sound level questions ask for the level and point at the local APV', function (string $field, string $expected) {
    expect(labelTemplateFor($field))->toBe($expected);
})->with([
    [
        'watIsDeGeluidsbelastingInDecibelDBANorm0103DBVanUwEvenementX',
        'Wat is de geluidsbelasting in db(A) van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? (Check de APV van de betreffende gemeente voor het maximaal aantal dbA dat is toegestaan)',
    ],
    [
        'watIsDeGeluidsbelastingInDecibelDBCNorm0103DBVanUwEvenement',
        'Wat is de geluidsbelasting in db(C) van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? (Check de APV van de betreffende gemeente voor het maximaal aantal dbC dat is toegestaan)',
    ],
]);

test('the previous wording with the hardcoded range is gone', function () {
    $code = file_get_contents(app_path('EventForm/Schema/Steps/VergunningaanvraagVervolgvragenStep.php'));

    expect($code)
        ->not->toContain('geluidsbelasting in decibel')
        ->not->toContain('0–103 dB')
        ->not->toContain('0–113 dB');
});

test('the sound level questions interpolate the event name', function (string $field, string $expected) {
    $state = FormState::empty();
    $state->setField('watIsDeNaamVanHetEvenementVergunning', 'Testfeest 2026');

    expect((new LabelRenderer)->render(labelTemplateFor($field), $state))->toBe($expected);
})->with([
    [
        'watIsDeGeluidsbelastingInDecibelDBANorm0103DBVanUwEvenementX',
        'Wat is de geluidsbelasting in db(A) van uw evenement Testfeest 2026? (Check de APV van de betreffende gemeente voor het maximaal aantal dbA dat is toegestaan)',
    ],
    [
        'watIsDeGeluidsbelastingInDecibelDBCNorm0103DBVanUwEvenement',
        'Wat is de geluidsbelasting in db(C) van uw evenement Testfeest 2026? (Check de APV van de betreffende gemeente voor het maximaal aantal dbC dat is toegestaan)',
    ],
]);

test('the sound level questions stay readable without an event name', function (string $field, string $expected) {
    expect((new LabelRenderer)->render(labelTemplateFor($field), FormState::empty()))->toBe($expected);
})->with([
    [
        'watIsDeGeluidsbelastingInDecibelDBANorm0103DBVanUwEvenementX',
        'Wat is de geluidsbelasting in db(A) van uw evenement ? (Check de APV van de betreffende gemeente voor het maximaal aantal dbA dat is toegestaan)',
    ],
    [
        'watIsDeGeluidsbelastingInDecibelDBCNorm0103DBVanUwEvenement',
        'Wat is de geluidsbelasting in db(C) van uw evenement ? (Check de APV van de betreffende gemeente voor het maximaal aantal dbC dat is toegestaan)',
    ],
]);
