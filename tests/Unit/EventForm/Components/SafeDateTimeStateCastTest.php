<?php

declare(strict_types=1);

use App\EventForm\Components\StateCasts\SafeDateTimeStateCast;

beforeEach(function () {
    $this->cast = new SafeDateTimeStateCast(
        format: 'Y-m-d H:i:s',
        internalFormat: 'Y-m-d H:i:s',
        timezone: 'Europe/Amsterdam',
    );
});

test('get returns null instead of throwing on a malformed value', function (string $value) {
    expect($this->cast->get($value))->toBeNull();
})->with([
    'five digit year' => '20256-09-20T16:00',
    'six digit year' => '202026-08-22T13:00',
]);

test('get still formats a clean value', function () {
    expect($this->cast->get('2026-08-22T13:00'))->toBeString();
});

test('get returns null for a blank value', function () {
    expect($this->cast->get(null))->toBeNull()
        ->and($this->cast->get(''))->toBeNull();
});
