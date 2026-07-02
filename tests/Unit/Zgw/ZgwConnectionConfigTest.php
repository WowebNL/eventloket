<?php

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;
use App\Services\Zgw\ZgwConnectionConfig;
use Illuminate\Support\Facades\Config;

it('formats a datum eigenschap as an 8-char date', function () {
    expect(ZgwConnectionConfig::formatEigenschapWaarde('2026-07-18T18:00:00', 'datum'))
        ->toBe('20260718');
});

it('formats a datum_tijd eigenschap as a 14-char datetime', function () {
    // RX Mission rejects a bare date for a datum_tijd eigenschap with a 400.
    expect(ZgwConnectionConfig::formatEigenschapWaarde('2026-07-18', 'datum_tijd'))
        ->toBe('20260718000000');
});

it('leaves a tekst eigenschap unchanged even when it parses as a date', function () {
    // A text value that happens to look like a date must never be mangled.
    expect(ZgwConnectionConfig::formatEigenschapWaarde('20260702', 'tekst'))->toBe('20260702');
    expect(ZgwConnectionConfig::formatEigenschapWaarde('2026', 'tekst'))->toBe('2026');
    expect(ZgwConnectionConfig::formatEigenschapWaarde('B', 'tekst'))->toBe('B');
});

it('leaves the value unchanged when the formaat is unknown or absent', function () {
    expect(ZgwConnectionConfig::formatEigenschapWaarde('2026-06-26'))->toBe('2026-06-26');
    expect(ZgwConnectionConfig::formatEigenschapWaarde('ZAAK-2026-0001', 'getal'))->toBe('ZAAK-2026-0001');
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
