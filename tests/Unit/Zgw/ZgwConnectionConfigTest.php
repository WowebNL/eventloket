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

    // Regression anchor: without a map the sets are the legacy three-step ones,
    // spelled out here rather than compared to the enum, so a change to either
    // side shows up.
    expect(ZgwConnectionConfig::documentVisibilityForRole('main', Role::Organiser))
        ->toBe(['zaakvertrouwelijk'])
        ->and(ZgwConnectionConfig::documentVisibilityForRole('main', Role::Advisor))
        ->toBe(['zaakvertrouwelijk', 'vertrouwelijk'])
        ->and(ZgwConnectionConfig::documentVisibilityForRole('main', Role::Reviewer))
        ->toBe(['zaakvertrouwelijk', 'vertrouwelijk', 'confidentieel'])
        // openbaar is deliberately absent: the defaults are not a maximum.
        ->and(ZgwConnectionConfig::documentVisibilityForRole('main', Role::Reviewer))
        ->not->toContain(DocumentVertrouwelijkheden::Openbaar->value);
});

it('derives the visible set from the maximum a connection configures', function () {
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => 'intern',
    ]);

    // A maximum is inclusive over the standard's order, so everything below it
    // comes along.
    expect(ZgwConnectionConfig::documentVisibilityForRole('gemeente_9', Role::Organiser))
        ->toBe(['openbaar', 'beperkt_openbaar', 'intern'])
        // A role without an entry still falls back to the enum default.
        ->and(ZgwConnectionConfig::documentVisibilityForRole('gemeente_9', Role::Advisor))
        ->toBe(DocumentVertrouwelijkheden::fromUserRole(Role::Advisor))
        ->and(ZgwConnectionConfig::documentVisibilityMaxForRole('gemeente_9', Role::Organiser))
        ->toBe('intern')
        ->and(ZgwConnectionConfig::documentVisibilityMaxForRole('gemeente_9', Role::Advisor))
        ->toBeNull();
});

it('reads a legacy set of levels as the maximum it expressed', function () {
    // Maps stored before the maximum was introduced hold the full set. The most
    // confidential member is the level the set granted access up to.
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => ['openbaar', 'beperkt_openbaar'],
        Role::Advisor->value => ['zaakvertrouwelijk', 'openbaar'],
        // An entry naming no level of the standard says nothing at all.
        Role::Reviewer->value => ['nonsense'],
    ]);

    expect(ZgwConnectionConfig::readVisibilityMax(['openbaar', 'intern', 'beperkt_openbaar']))
        ->toBe('intern')
        ->and(ZgwConnectionConfig::readVisibilityMax([]))->toBeNull()
        ->and(ZgwConnectionConfig::documentVisibilityMaxForRole('gemeente_9', Role::Organiser))
        ->toBe('beperkt_openbaar')
        // Order within the stored set does not matter, only confidentiality.
        ->and(ZgwConnectionConfig::documentVisibilityMaxForRole('gemeente_9', Role::Advisor))
        ->toBe('zaakvertrouwelijk')
        ->and(ZgwConnectionConfig::documentVisibilityForRole('gemeente_9', Role::Advisor))
        ->toBe(['openbaar', 'beperkt_openbaar', 'intern', 'zaakvertrouwelijk'])
        ->and(ZgwConnectionConfig::documentVisibilityForRole('gemeente_9', Role::Reviewer))
        ->toBe(DocumentVertrouwelijkheden::fromUserRole(Role::Reviewer));
});

it('falls back to the legacy upload defaults on a connection without a map', function () {
    Config::set('zgw.connections.main.vertrouwelijkheid_map', null);

    // An organiser never gets the choice select, so this default carries all of
    // their uploads. Without a configured maximum the visible sets start at
    // zaakvertrouwelijk, so openbaar would be visible to nobody: the legacy
    // default stands.
    expect(ZgwConnectionConfig::uploadDefaultForRole('main', Role::Organiser))
        ->toBe(DocumentVertrouwelijkheden::Zaakvertrouwelijk->value)
        ->and(ZgwConnectionConfig::uploadDefaultForRole('main', Role::Advisor))
        ->toBe(DocumentVertrouwelijkheden::Vertrouwelijk->value)
        ->and(ZgwConnectionConfig::uploadDefaultForRole('main', Role::Reviewer))
        ->toBe(DocumentVertrouwelijkheden::Vertrouwelijk->value);
});

it('defaults an organiser upload to openbaar on a connection with a maximum', function () {
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => 'openbaar',
        Role::Advisor->value => 'beperkt_openbaar',
        Role::Reviewer->value => 'intern',
    ]);

    // Here openbaar sits at or below every maximum, so an organiser upload is
    // visible to every role group. The other roles keep the legacy default.
    expect(ZgwConnectionConfig::uploadDefaultForRole('gemeente_9', Role::Organiser))
        ->toBe(DocumentVertrouwelijkheden::Openbaar->value)
        ->and(ZgwConnectionConfig::uploadDefaultForRole('gemeente_9', Role::Advisor))
        ->toBe(DocumentVertrouwelijkheden::Vertrouwelijk->value);
});

it('never defaults an organiser upload to a level the connection hides', function () {
    // The failure this guards against: an organiser gets no choice select, so a
    // default outside every group's visible set produces a document nobody can
    // open, the organiser included. Asserted for both regimes.
    Config::set('zgw.connections.main.vertrouwelijkheid_map', null);
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => 'openbaar',
        Role::Advisor->value => 'beperkt_openbaar',
        Role::Reviewer->value => 'intern',
    ]);

    foreach (['main', 'gemeente_9'] as $connection) {
        $default = ZgwConnectionConfig::uploadDefaultForRole($connection, Role::Organiser);

        foreach ([Role::Organiser, Role::Advisor, Role::Reviewer] as $role) {
            expect(ZgwConnectionConfig::documentVisibilityForRole($connection, $role))
                ->toContain($default);
        }
    }
});

it('reads the distinct maximum levels a connection map configures', function () {
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => 'openbaar',
        Role::Advisor->value => 'beperkt_openbaar',
        Role::Reviewer->value => 'intern',
        // The fanned-out municipal roles repeat the gemeente maximum.
        Role::Coordinator->value => 'intern',
    ]);

    // Ordered from the least to the most confidential, deduplicated.
    expect(ZgwConnectionConfig::configuredVisibilityMaxLevels('gemeente_9'))
        ->toBe(['openbaar', 'beperkt_openbaar', 'intern'])
        // A connection without a map configures nothing of its own.
        ->and(ZgwConnectionConfig::configuredVisibilityMaxLevels('main'))
        ->toBe([]);
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

it('reports a connection as OneGround only when the flag is set', function () {
    Config::set('zgw.connections.gemeente_9.is_oneground', true);
    Config::set('zgw.connections.main.is_oneground', false);

    expect(ZgwConnectionConfig::isOneGround('gemeente_9'))->toBeTrue()
        ->and(ZgwConnectionConfig::isOneGround('main'))->toBeFalse()
        // An unset flag defaults to false.
        ->and(ZgwConnectionConfig::isOneGround('gemeente_42'))->toBeFalse();
});

it('allows organiser withdrawal by default and honours the toggle', function () {
    Config::set('zgw.connections.gemeente_9.allow_organiser_withdrawal', false);

    expect(ZgwConnectionConfig::allowsOrganiserWithdrawal('main'))->toBeTrue()
        ->and(ZgwConnectionConfig::allowsOrganiserWithdrawal('gemeente_9'))->toBeFalse();
});

it('always blocks organiser withdrawal on a OneGround connection', function () {
    // Even with withdrawal explicitly enabled, OneGround blocks it.
    Config::set('zgw.connections.gemeente_9.allow_organiser_withdrawal', true);
    Config::set('zgw.connections.gemeente_9.is_oneground', true);

    expect(ZgwConnectionConfig::allowsOrganiserWithdrawal('gemeente_9'))->toBeFalse();
});
