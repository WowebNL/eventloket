<?php

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;
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

it('formats a datum_tijd eigenschap as a 14-char datetime regardless of the connection format', function () {
    // The catalogus formaat wins: even with a date-only connection default, a
    // datum_tijd eigenschap must be sent as YYYYMMDDHHMMSS (RX Mission rejects a
    // bare date with a 400).
    Config::set('zgw.connections.gemeente_1.eigenschap_date_format', 'Ymd');

    expect(ZgwConnectionConfig::formatEigenschapWaarde('gemeente_1', '2026-07-18', 'datum_tijd'))
        ->toBe('20260718000000');
});

it('formats a datum eigenschap as an 8-char date', function () {
    expect(ZgwConnectionConfig::formatEigenschapWaarde('main', '2026-07-18T18:00:00', 'datum'))
        ->toBe('20260718');
});

it('never date-formats a tekst eigenschap even when the connection sets a date format', function () {
    // A tekst value that happens to parse as a date must not be mangled.
    Config::set('zgw.connections.gemeente_1.eigenschap_date_format', 'Ymd');

    expect(ZgwConnectionConfig::formatEigenschapWaarde('gemeente_1', '20260702', 'tekst'))
        ->toBe('20260702');
    expect(ZgwConnectionConfig::formatEigenschapWaarde('gemeente_1', '2026', 'tekst'))
        ->toBe('2026');
});

it('falls back to the configured RSIN for bronorganisatie', function () {
    Config::set('zgw.connections.main.bronorganisatie_rsin', '820151130');

    expect(ZgwConnectionConfig::bronorganisatie('main'))->toBe('820151130');
});

it('uses the connection RSIN for bronorganisatie when set', function () {
    Config::set('zgw.connections.gemeente_9.bronorganisatie_rsin', '999999999');

    expect(ZgwConnectionConfig::bronorganisatie('gemeente_9'))->toBe('999999999');
});

it('falls back to the enum defaults for document visibility', function () {
    Config::set('zgw.connections.main.vertrouwelijkheid_map', null);

    expect(ZgwConnectionConfig::documentVisibilityForRole('main', Role::Organiser))
        ->toBe(DocumentVertrouwelijkheden::fromUserRole(Role::Organiser))
        ->and(ZgwConnectionConfig::documentVisibilityForRole('main', Role::Reviewer))
        ->toBe(DocumentVertrouwelijkheden::fromUserRole(Role::Reviewer));
});

it('uses the connection visibility map when configured', function () {
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => ['openbaar', 'zaakvertrouwelijk'],
    ]);

    expect(ZgwConnectionConfig::documentVisibilityForRole('gemeente_9', Role::Organiser))
        ->toBe(['openbaar', 'zaakvertrouwelijk'])
        // A role without an entry still falls back to the enum default.
        ->and(ZgwConnectionConfig::documentVisibilityForRole('gemeente_9', Role::Advisor))
        ->toBe(DocumentVertrouwelijkheden::fromUserRole(Role::Advisor));
});

it('falls back to the legacy upload defaults per role', function () {
    Config::set('zgw.connections.main.vertrouwelijkheid_map', null);

    expect(ZgwConnectionConfig::uploadDefaultForRole('main', Role::Organiser))
        ->toBe(DocumentVertrouwelijkheden::Zaakvertrouwelijk->value)
        ->and(ZgwConnectionConfig::uploadDefaultForRole('main', Role::Advisor))
        ->toBe(DocumentVertrouwelijkheden::Vertrouwelijk->value)
        ->and(ZgwConnectionConfig::uploadDefaultForRole('main', Role::Reviewer))
        ->toBe(DocumentVertrouwelijkheden::Vertrouwelijk->value);
});

it('uses the connection upload default per role when configured', function () {
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.upload_default', [
        Role::Organiser->value => 'openbaar',
    ]);

    expect(ZgwConnectionConfig::uploadDefaultForRole('gemeente_9', Role::Organiser))
        ->toBe('openbaar')
        ->and(ZgwConnectionConfig::uploadDefaultForRole('gemeente_9', Role::Advisor))
        ->toBe(DocumentVertrouwelijkheden::Vertrouwelijk->value);
});

it('falls back to zaakvertrouwelijk for system uploads', function () {
    Config::set('zgw.connections.main.vertrouwelijkheid_map', null);

    expect(ZgwConnectionConfig::systemUploadDefault('main'))
        ->toBe(DocumentVertrouwelijkheden::Zaakvertrouwelijk->value);
});

it('uses the connection system upload default when configured', function () {
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.upload_default.system', 'vertrouwelijk');

    expect(ZgwConnectionConfig::systemUploadDefault('gemeente_9'))->toBe('vertrouwelijk');
});
