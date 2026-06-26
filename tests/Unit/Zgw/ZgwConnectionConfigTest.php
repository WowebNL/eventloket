<?php

use App\Services\Zgw\ZgwConnectionConfig;
use Illuminate\Support\Facades\Config;

it('keeps the eigenschap value unchanged when no date format is configured', function () {
    Config::set('zgw.connections.main.eigenschap_date_format', null);

    expect(ZgwConnectionConfig::formatEigenschapWaarde('main', '2026-06-26'))->toBe('2026-06-26');
});

it('reformats a parseable date when an eigenschap date format is configured', function () {
    Config::set('zgw.connections.main.eigenschap_date_format', 'YmdHis');

    expect(ZgwConnectionConfig::formatEigenschapWaarde('main', '2026-06-26 14:30:00'))->toBe('20260626143000');
});

it('leaves a non-date value unchanged even when a date format is configured', function () {
    Config::set('zgw.connections.main.eigenschap_date_format', 'YmdHis');

    expect(ZgwConnectionConfig::formatEigenschapWaarde('main', 'ZAAK-2026-0001'))->toBe('ZAAK-2026-0001');
});
